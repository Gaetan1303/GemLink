import unittest

from schema import OpticalProperties


class OpticalPropertiesNormalizationTest(unittest.TestCase):
    def base_payload(self, indice):
        return {
            "couleur": "Violet",
            "eclat": "Vitreux",
            "transparence": "Transparent",
            "indice_refraction": indice,
        }

    def test_accepts_numeric_refraction_index(self):
        result = OpticalProperties.model_validate(self.base_payload(1.544))
        self.assertEqual("1.544", result.indice_refraction)

    def test_accepts_refraction_index_as_list(self):
        result = OpticalProperties.model_validate(self.base_payload([1.544, 1.553]))
        self.assertEqual("1.544 - 1.553", result.indice_refraction)

    def test_accepts_refraction_index_as_bounds_object(self):
        result = OpticalProperties.model_validate(
            self.base_payload({"min": 1.544, "max": 1.553})
        )
        self.assertEqual("1.544 - 1.553", result.indice_refraction)

    def test_normalizes_accented_key_with_spaces(self):
        payload = self.base_payload(None)
        payload.pop("indice_refraction")
        payload["Indice de réfraction"] = "1.54 - 1.55"

        result = OpticalProperties.model_validate(payload)
        self.assertEqual("1.54 - 1.55", result.indice_refraction)

    def test_missing_refraction_index_has_explicit_fallback(self):
        payload = self.base_payload(None)
        payload.pop("indice_refraction")

        result = OpticalProperties.model_validate(payload)
        self.assertEqual("Non déterminé", result.indice_refraction)
