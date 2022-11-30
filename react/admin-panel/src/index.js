import React from "react";
import {createRoot} from "react-dom/client";
import AdminDashboard from "./App";

const root = createRoot(document.getElementById('admin-panel'));
root.render(<AdminDashboard />);
