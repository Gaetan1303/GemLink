import { Component, computed, inject } from '@angular/core';
import {SharedModule} from "../../../shared/shared-module";
import { NavBarMobile } from "../../../components/nav-bar-mobile/nav-bar-mobile";
import {AuthService} from "../../../core/services/auth";
import { MenuRole } from '../../../components/menu-burger/menu-navigation.model';

@Component({
  selector: 'app-presse',
  imports: [SharedModule, NavBarMobile],
  templateUrl: './presse.html',
  styleUrls: ['./presse.scss'],
})
export class Presse {
    private readonly authService = inject(AuthService);
    protected readonly currentRole = computed<MenuRole>(
    () => this.authService.currentUser()?.role ?? 'VISITEUR'
  );
}
