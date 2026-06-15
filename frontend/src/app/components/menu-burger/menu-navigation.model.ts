export interface MenuItem {
  label: string;
  route: string;
  iconClass: string; 
}

export type MenuRole = 'visiteur' | 'user' | 'admin';

export const NAVIGATION_MENUS: Record<MenuRole, MenuItem[]> = {
  visiteur: [
    { label: 'Connexion', route: '/auth/login', iconClass: 'icon-login' },
    { label: 'Inscription', route: '/auth/register', iconClass: 'icon-register' }
  ],
  user: [
    { label: 'Faction', route: '/factions', iconClass: 'icon-faction' },
    { label: 'Badge', route: '/badges', iconClass: 'icon-badge' },
    { label: 'Profil', route: '/users/me', iconClass: 'icon-profile' },
    { label: 'Collection', route: '/collections', iconClass: 'icon-collection' },
    { label: 'Retour', route: '/', iconClass: 'icon-back' }
  ],
  admin: [
    { label: 'Dashboard', route: '/admin/stats', iconClass: 'icon-dashboard' },
    { label: 'Modération', route: '/admin/reports', iconClass: 'icon-moderation' },
    { label: 'Retour', route: '/', iconClass: 'icon-back' }
  ]
};