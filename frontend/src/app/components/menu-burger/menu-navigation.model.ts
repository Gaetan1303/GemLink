export interface MenuItem {
  label: string;
  route: string;
  iconClass: string;
}

export type MenuRole = 'VISITEUR' | 'ROLE_USER' | 'ROLE_MODERATOR' | 'ROLE_ADMIN';

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
      { label: 'Classement',  route: '/leaderboard',   iconClass: 'leaderboard' },
      { label: 'Connexion',   route: '/auth/login',    iconClass: 'login' },
      { label: 'Inscription', route: '/auth/register', iconClass: 'person_add' },
    ],
  },
  ROLE_USER: {
    navItems: [
      { label: 'Accueil',    route: '/',            iconClass: 'home' },
      { label: 'Post',       route: '/posts',        iconClass: 'near_me' },
      { label: 'Identifier', route: '/posts/new',  iconClass: 'center_focus_strong' },
      { label: 'Profil',     route: '/users/me',    iconClass: 'person' },
      { label: 'Galerie',    route: '/vitrine', iconClass: 'collections' },
    ],
    menuItems: [
      { label: 'Accueil',     route: '/',            iconClass: 'home' },
      { label: 'Post',        route: '/posts',        iconClass: 'near_me' },
      { label: 'Identifier',  route: '/posts/new',  iconClass: 'center_focus_strong' },
      { label: 'Profil',      route: '/users/me',    iconClass: 'person' },
      { label: 'Galerie',     route: '/vitrine', iconClass: 'collections' },
      { label: 'Notifications', route: '/notifications', iconClass: 'notifications' },
      { label: 'Classement', route: '/leaderboard', iconClass: 'leaderboard' },
      { label: 'Faction',     route: '/factions',    iconClass: 'groups' },
      { label: 'Badge',       route: '/badges',      iconClass: 'military_tech' },
      { label: 'Déconnexion', route: '/auth/logout', iconClass: 'logout' },
    ],
  },
  ROLE_MODERATOR: {
    navItems: [
      { label: 'Modération', route: '/admin/moderation', iconClass: 'shield' },
      { label: 'Retour', route: '/', iconClass: 'arrow_back' },
      { label: 'Déconnexion', route: '/auth/logout', iconClass: 'logout' },
    ],
    menuItems: [
      { label: 'Modération', route: '/admin/moderation', iconClass: 'shield' },
      { label: 'Retour', route: '/', iconClass: 'arrow_back' },
      { label: 'Déconnexion', route: '/auth/logout', iconClass: 'logout' },
    ],
  },
  ROLE_ADMIN: {
    navItems: [
      { label: 'Dashboard',  route: '/admin',           iconClass: 'dashboard' },
      { label: 'Modération', route: '/admin/moderation', iconClass: 'shield' },
      { label: 'Retour',     route: '/',                iconClass: 'arrow_back' },
      { label: 'Déconnexion', route: '/auth/logout', iconClass: 'logout' },
    ],
    menuItems: [
      { label: 'Dashboard',  route: '/admin',           iconClass: 'dashboard' },
      { label: 'Modération', route: '/admin/moderation', iconClass: 'shield' },
      { label: 'Retour',     route: '/',                iconClass: 'arrow_back' },
      { label: 'Déconnexion', route: '/auth/logout', iconClass: 'logout' },
    ],
  },
};
