import React from "react";
import {createRoot} from "react-dom/client";
import App from "./App";
import {LocaleProvider} from "shared/locale";

const root = createRoot(document.getElementById('{{MODULE_NAME}}'));
root.render(<LocaleProvider>
    <App />
</LocaleProvider>);
