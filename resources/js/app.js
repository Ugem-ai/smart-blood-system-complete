import './bootstrap';
import { createApp } from 'vue';
import router from './router';
import App from './App.vue';
import { registerDeviceToken, startDeviceTokenRefresh } from './lib/deviceToken';

const app = createApp(App);

app.use(router);
app.mount('#app');

registerDeviceToken().catch(() => {
	// Best effort only.
});
startDeviceTokenRefresh();
