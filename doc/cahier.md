# Projet “GemLink” — Cahier des charges

## 1. Synopsis et vision

### Contexte et concept
Le projet « GemLink » est une plateforme web qui combine :
- un réseau social spécialisé dans les pierres et minéraux,
- une reconnaissance visuelle assistée par intelligence artificielle.

La plateforme permet aux utilisateurs de :
- photographier une pierre,
- obtenir une identification automatique,
- partager leur découverte,
- interagir avec une communauté de passionnés.

Le projet se distingue par un système de confiance communautaire (« Trust Score ») qui améliore progressivement les modèles IA grâce aux validations des utilisateurs les plus fiables.

### Personas
Quatre profils principaux utilisent la plateforme :
- Grand public curieux,
- Collectionneurs passionnés,
- Experts minéralogistes,
- Modérateurs / administrateurs.

### Fonctionnalités clés
- Reconnaissance IA d’une image de pierre.
- Publication et partage de posts avec image ou courtes vidéos.
- Collecte des votes positifs/négatifs sur les identifications.
- Création de collections publiques de pierres partageables par lien ou QR code.
- Système de réputation : points, badges, niveaux, Trust Score.

### Mission
Valoriser la passion minéralogique en combinant :
- l’accessibilité d’un réseau social,
- l’aspect collaboratif d’une communauté,
- la précision d’un outil IA.

### Phases de développement
- **Phase 1 – MVP social** : authentification, profils, posts, feed, interactions.
- **Phase 2 – IA & recherche** : pipeline IA, embeddings, recherche de similarité, traitement asynchrone.
- **Phase 3 – Performance & UX** : Redis, pagination infinie, lazy loading, Setups.
- **Phase 4 – Gamification & modération** : points, niveaux, badges, Trust System, dashboard de modération.

---

## 2. User stories

### Epic 1 — Authentification et gestion des profils

#### 1.1 Inscription
En tant qu’utilisateur, je veux pouvoir m’inscrire avec mon pseudo, email et mot de passe pour créer un compte.

**Critères d’acceptation** :
- Formulaire avec pseudo, email et mot de passe.
- Mot de passe sécurisé : minimum 8 caractères, une majuscule, une minuscule, un chiffre, un caractère spécial.
- Email au format valide et unique en base.
- Impossible de créer un compte si l’email existe déjà.

#### 1.2 Validation d’email
En tant qu’utilisateur, je veux recevoir un email de validation pour confirmer mon inscription.

**Critères d’acceptation** :
- Envoi d’un email de validation après inscription.
- Compte inactif tant que l’email n’est pas validé.
- Lien de validation expirant (ex : 1H).
- Message de confirmation après clic sur le lien.

#### 1.3 Connexion
En tant qu’utilisateur, je veux pouvoir me connecter avec mon email et mot de passe pour accéder à mon compte.

**Critères d’acceptation** :
- Formulaire de connexion avec email et mot de passe.
- Message d’erreur générique pour identifiants invalides.
- Compte non validé interdit de se connecter.

#### 1.4 Authentification token
En tant qu’utilisateur, je veux recevoir un JWT et un refresh token après une connexion réussie pour maintenir ma session.

**Critères d’acceptation** :
- JWT avec durée de vie limitée (15–30 min).
- Refresh token généré et stocké côté serveur.
- Refresh token permet de renouveler le JWT sans reconnexion.

#### 1.5 Déconnexion
En tant qu’utilisateur, je veux pouvoir me déconnecter pour sécuriser mon compte.

**Critères d’acceptation** :
- Bouton de déconnexion disponible.
- JWT invalidé côté serveur.
- Refresh token révoqué en cache.

#### 1.6 Réinitialisation de mot de passe
En tant qu’utilisateur, je veux pouvoir réinitialiser mon mot de passe en cas d’oubli.

**Critères d’acceptation** :
- Lien « Mot de passe oublié » sur l’écran de login.
- Email sécurisé de réinitialisation envoyé.
- Lien de reset expirant après un délai défini (ex : 1H).
- Nouveau mot de passe conforme aux règles de sécurité.

#### 1.7 Modification de profil
En tant qu’utilisateur, je veux pouvoir modifier mon profil pour mettre à jour mes informations.

**Critères d’acceptation** :
- Modification de pseudo, bio et avatar.
- Avatar limité en taille et format (JPG/PNG max 2 Mo).
- Changements immédiatement visibles sur le profil public.
- Un utilisateur ne peut modifier que son propre profil.

---

### Epic 2 — Réseau social : posts, feed et interactions

#### 2.1 Publication de contenu
En tant qu’utilisateur, je veux publier un post avec image ou vidéo pour partager mes découvertes.

**Critères d’acceptation** :
- Acceptation image (JPG/PNG/WEBP) ou vidéo (MP4).
- Taille maximale des fichiers respectée.
- Post sans média valide refusé.
- Post visible immédiatement dans le feed.

#### 2.2 Titre, description et tags
En tant qu’utilisateur, je veux ajouter un titre, une description et des tags à mes posts.

**Critères d’acceptation** :
- Titre et description facultatifs mais correctement enregistrés.
- Tags exploitables pour le filtrage.
- Tags ajoutés automatiquement après analyse.

#### 2.3 Feed de contenu
En tant qu’utilisateur, je veux voir un feed de posts pour découvrir du contenu.

**Critères d’acceptation** :
- Feed paginé (cursor-based).
- Scroll infini sans rechargement complet.
- Posts triés selon pertinence ou date.
- Chargement en moins de 200ms hors cache froid.

#### 2.4 Likes
En tant qu’utilisateur, je veux pouvoir liker un post pour montrer mon intérêt.

**Critères d’acceptation** :
- Un utilisateur peut liker et unlike un post.
- Like comptabilisé en temps réel.
- Un utilisateur ne peut liker qu’une seule fois.

#### 2.5 Commentaires
En tant qu’utilisateur, je veux pouvoir commenter un post pour interagir.

**Critères d’acceptation** :
- Commentaire ajouté ou supprimé par son auteur.
- Commentaires affichés par ordre chronologique.
- Auteur du post notifié.

#### 2.6 Résultats IA sur les posts
En tant qu’utilisateur, je veux voir les résultats de l’analyse IA sur mes posts.

**Critères d’acceptation** :
- Statut « analyse en cours » tant que l’IA n’a pas répondu.
- Résultat IA avec label et score de confiance.
- Résultat mis à jour automatiquement.

#### 2.7 Posts similaires
En tant qu’utilisateur, je veux voir des posts similaires à un post donné.

**Critères d’acceptation** :
- Section « posts similaires » avec jusqu’à 5 résultats.
- Résultats basés sur similarité d’embedding.
- Résultats pertinents visuellement et sémantiquement.

---

### Epic 3 — Reconnaissance IA

#### 3.1 Analyse automatique des images
En tant qu’utilisateur, je veux que mes images soient analysées automatiquement pour identifier les pierres.

**Critères d’acceptation** :
- Upload déclenche automatiquement le pipeline IA.
- Analyse ne bloque pas la création du post.
- Résultat retourné même en cas de faible confiance.

#### 3.2 Identification avec niveau de confiance
En tant qu’utilisateur, je veux obtenir une identification de pierre avec un niveau de confiance.

**Critères d’acceptation** :
- Résultat avec label de minéral.
- Score de confiance affiché (0–100%).
- Indication « incertain » pour faible confiance.

#### 3.3 Amélioration communautaire de l’IA
En tant qu’utilisateur, je veux que mes images contribuent à améliorer l’IA via la communauté.

**Critères d’acceptation** :
- Validations utilisateur enregistrées.
- Validations influencent l’entraînement futur.
- Contributions pondérées par Trust Score.

---

### Epic 4 — Vitrine (Collections)

#### 4.1 Création de collections
En tant qu’utilisateur, je veux créer une collection de pierres pour organiser mes découvertes.

**Critères d’acceptation** :
- Collection avec titre et description.
- Collection pouvant contenir plusieurs posts.
- Collection non publiée si elle est vide.

#### 4.2 Page publique de la collection
En tant qu’utilisateur, je veux accéder à une page publique de ma collection.

**Critères d’acceptation** :
- URL unique (slug) pour chaque collection.
- Page accessible sans authentification.
- Comptabilisation des vues.

#### 4.3 QR code de partage
En tant qu’utilisateur, je veux générer un QR code pour partager ma collection.

**Critères d’acceptation** :
- QR code généré automatiquement.
- QR code pointe vers la page publique de la collection.
- QR code téléchargeable.

---

### Epic 5 — Gamification et Trust System

#### 5.1 Points d’activité
En tant qu’utilisateur, je veux gagner des points pour mes actions sur la plateforme.

**Critères d’acceptation** :
- Points attribués pour posts, likes, validations.
- Points mis à jour en temps réel.
- Historique des points traçable.

#### 5.2 Progression de niveau
En tant qu’utilisateur, je veux progresser en niveaux pour visualiser mon expertise.

**Critères d’acceptation** :
- Niveaux basés sur des seuils de points.
- Niveau affiché sur le profil.
- Progression visible (barre ou pourcentage).

#### 5.3 Badges
En tant qu’utilisateur, je veux obtenir des badges pour mes réalisations.

**Critères d’acceptation** :
- Badges attribués automatiquement.
- Badge attribué une seule fois.
- Notification envoyée lors de l’obtention.

#### 5.4 Classement
En tant qu’utilisateur, je veux voir un tableau de succès personnel.

**Critères d’acceptation** :
- Classement mis à jour régulièrement via cache.
- Chaque utilisateur voit sa progression et de ses succès.

---

### Epic 6 — Modération et administration

#### 6.1 Signalement de contenu
En tant qu’utilisateur, je veux pouvoir signaler un contenu inapproprié.

**Critères d’acceptation** :
- Motif obligatoire pour chaque signalement.
- Signalement enregistré en base.
- Auteur du post notifié (optionnel selon règles produit).

#### 6.2 Traitement des signalements
En tant que modérateur, je veux traiter les signalements pour maintenir la qualité du contenu.

**Critères d’acceptation** :
- Signalements visibles dans un dashboard.
- Possibilité de valider ou rejeter un signalement.
- Action tracée dans un audit log.

#### 6.3 Gestion administrative
En tant qu’administrateur, je veux gérer les utilisateurs et les contenus.

**Critères d’acceptation** :
- Suppression de post, commentaire ou utilisateur.
- Bannissement et débannissement d’utilisateur.
- Toutes les actions admin tracées.

#### 6.4 Supervision des statistiques
En tant qu’administrateur, je veux superviser les statistiques de la plateforme.

**Critères d’acceptation** :
- KPIs : posts, utilisateurs, IA, activité.
- Données mises à jour régulièrement.
- Métriques cohérentes avec la base.


---

## 3. Architecture technique

### Backend