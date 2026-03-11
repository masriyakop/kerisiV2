import { createApp } from "vue";
import { createPinia } from "pinia";

import App from "./App.vue";
import router from "./router";
import { useUiThemeStore } from "@/stores/uiTheme";
import "./style.css";

const app = createApp(App);
const pinia = createPinia();
app.use(pinia);
useUiThemeStore(pinia).initFromStorage();
app.use(router);
app.mount("#app");
