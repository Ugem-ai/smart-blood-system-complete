import { createRouter, createWebHistory } from 'vue-router';
import { currentUser, isAuthenticated } from './lib/auth';

const AdminDashboard = () => import('./pages/AdminDashboard.vue');
const ConfirmPassword = () => import('./pages/ConfirmPassword.vue');
const DonorDashboard = () => import('./pages/DonorDashboard.vue');
const ForgotPassword = () => import('./pages/ForgotPassword.vue');
const HospitalDashboard = () => import('./pages/HospitalDashboard.vue');
const Login = () => import('./pages/Login.vue');
const NotFound = () => import('./pages/NotFound.vue');
const Profile = () => import('./pages/Profile.vue');
const Register = () => import('./pages/Register.vue');
const ResetPassword = () => import('./pages/ResetPassword.vue');

const routes = [
  { path: '/', redirect: '/login' },
  { path: '/login', component: Login, meta: { guestOnly: true } },
  { path: '/register', component: Register, props: { initialRole: 'donor' }, meta: { guestOnly: true } },
  { path: '/register/hospital', component: Register, props: { initialRole: 'hospital' }, meta: { guestOnly: true } },
  { path: '/forgot-password', component: ForgotPassword, meta: { guestOnly: true } },
  { path: '/reset-password/:token', component: ResetPassword, props: true, meta: { guestOnly: true } },
  { path: '/admin/dashboard', component: AdminDashboard, meta: { requiresAuth: true, role: 'admin' } },
  { path: '/hospital/dashboard', component: HospitalDashboard, meta: { requiresAuth: true, role: 'hospital' } },
  { path: '/donor/dashboard', component: DonorDashboard, meta: { requiresAuth: true, role: 'donor' } },
  { path: '/profile', component: Profile, meta: { requiresAuth: true } },
  { path: '/settings', component: Profile, meta: { requiresAuth: true } },
  { path: '/confirm-password', component: ConfirmPassword, meta: { requiresAuth: true } },
  { path: '/blood-requests', redirect: '/hospital/dashboard' },
  { path: '/donation-history', redirect: '/donor/dashboard' },
  { path: '/:pathMatch(.*)*', component: NotFound },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to) => {
  const authenticated = isAuthenticated();
  const user = currentUser();

  if (to.meta.requiresAuth && !authenticated) {
    return '/login';
  }

  if (to.meta.guestOnly && authenticated && user?.role) {
    return `/${user.role}/dashboard`;
  }

  if (to.meta.role && user?.role && to.meta.role !== user.role) {
    return `/${user.role}/dashboard`;
  }

  return true;
});

export default router;
