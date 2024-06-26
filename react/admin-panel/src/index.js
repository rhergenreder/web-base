import React from "react";
import {createRoot} from "react-dom/client";
import App from "./App";
import {LocaleProvider} from "shared/locale";

const root = createRoot(document.getElementById('admin-panel'));
root.render(<LocaleProvider>
    <App />
</LocaleProvider>);
