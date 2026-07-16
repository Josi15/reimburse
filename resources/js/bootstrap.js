import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Sanctum SPA: kirim cookie sesi + XSRF-TOKEN pada request API same-domain.
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;
