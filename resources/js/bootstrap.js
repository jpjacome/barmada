import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Broadcasting is not wired (BROADCAST_CONNECTION=log); the Echo/Pusher
// client that used to live here logged undefined credentials to the
// console on every auth page and shipped ~90KB nobody used.
