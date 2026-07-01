import './bootstrap';
import { createApp } from 'vue';
import router from './router';
import App from './App.vue';
import { registerDeviceToken, startDeviceTokenRefresh } from './lib/deviceToken';

// Recover from stale cached Vite chunks after deployments.
if (typeof window !== 'undefined') {
	window.addEventListener('vite:preloadError', (event) => {
		event.preventDefault();
		window.location.reload();
	});
}

const app = createApp(App);

app.use(router);
app.mount('#app');

registerDeviceToken().catch(() => {
	// Best effort only.
});
startDeviceTokenRefresh();
