export interface MenuItem {
  label: string;
  route: string;
  iconClass: string;
}

export type MenuRole = 'VISITEUR' | 'ROLE_USER' | 'ROLE_ADMIN';

export interface MenuConfig {
  navItems: MenuItem[];  // nav-bar-mobile (5 max)
  menuItems: MenuItem[]; // menu-burger (tout)
}

export const NAVIGATION_MENUS: Record<MenuRole, MenuConfig> = {
  VISITEUR: {
    navItems: [
      { label: 'Accueil',     route: '/',              iconClass: 'home' },
      { label: 'Connexion',   route: '/auth/login',    iconClass: 'login' },
      { label: 'Inscription', route: '/auth/register', iconClass: 'person_add' },
    ],
    menuItems: [
      { label: 'Accueil',     route: '/',              iconClass: 'home' },
      { label: 'Connexion',   route: '/auth/login',    iconClass: 'login' },
      { label: 'Inscription', route: '/auth/register', iconClass: 'person_add' },
    ],
  },
  ROLE_USER: {
    navItems: [
      { label: 'Accueil',    route: '/',            iconClass: 'home' },
      { label: 'Post',       route: '/posts',        iconClass: 'near_me' },
      { label: 'Identifier', route: '/identifier',  iconClass: 'center_focus_strong' },
      { label: 'Profil',     route: '/users/me',    iconClass: 'person' },
      { label: 'Galerie',    route: '/vitrine', iconClass: 'collections' },
    ],
    menuItems: [
      { label: 'Accueil',     route: '/',            iconClass: 'home' },
      { label: 'Post',        route: '/posts',        iconClass: 'near_me' },
      { label: 'Identifier',  route: '/identifier',  iconClass: 'center_focus_strong' },
      { label: 'Profil',      route: '/users/me',    iconClass: 'person' },
      { label: 'Galerie',     route: '/vitrine', iconClass: 'collections' },
      { label: 'Faction',     route: '/factions',    iconClass: 'groups' },
      { label: 'Badge',       route: '/badges',      iconClass: 'military_tech' },
      { label: 'Déconnexion', route: '/auth/logout', iconClass: 'logout' },
    ],
  },
  ROLE_ADMIN: {
    navItems: [
      { label: 'Accueil',    route: '/',                iconClass: 'home' },
      { label: 'Dashboard',  route: '/admin/stats',     iconClass: 'dashboard' },
      { label: 'Modération', route: '/admin/reports',   iconClass: 'shield' },
      { label: 'Déconnexion', route: '/auth/logout', iconClass: 'logout' },
    ],
    menuItems: [
      { label: 'Accueil',    route: '/',                iconClass: 'home' },
      { label: 'Dashboard',  route: '/admin/stats',     iconClass: 'dashboard' },
      { label: 'Modération', route: '/admin/reports',   iconClass: 'shield' },
    ],
  },
};