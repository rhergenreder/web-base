import {USER_GROUP_ADMIN} from "./constants";
import {isInt} from "./util";

export default class API {
    constructor() {
        this.loggedIn = false;
        this.user = null;
        this.session = null;
        this.permissions = [];
    }

    csrfToken() {
        return this.loggedIn ? this.session.csrfToken : null;
    }

    async apiCall(method, params) {
        params = params || { };
        const csrfToken = this.csrfToken();
        const config = {method: 'post'};
        if (params instanceof FormData) {
            if (csrfToken) {
                params.append("csrfToken", csrfToken);
            }
            config.body = params;
        } else {
            if (csrfToken) {
                params.csrfToken = csrfToken;
            }
            config.headers = {'Content-Type': 'application/json'};
            config.body = JSON.stringify(params);
        }

        let response = await fetch("/api/" + method, config);
        let res = await response.json();
        if (!res.success && res.msg === "You are not logged in.") {
            this.loggedIn = false;
        }

        return res;
    }

    hasPermission(method) {
        if (!this.permissions) {
            return false;
        }

        for (const permission of this.permissions) {
            if (method.endsWith("*") && permission.toLowerCase().startsWith(method.toLowerCase().substring(0, method.length - 1))) {
                return true;
            } else if (method.toLowerCase() === permission.toLowerCase()) {
                return true;
            }
        }

        return false;
    }


    hasGroup(groupIdOrName) {
        if (this.loggedIn && this.user?.groups) {
            if (isInt(groupIdOrName)) {
                return this.user.groups.hasOwnProperty(groupIdOrName);
            } else {
                let userGroups = Object.values(this.user.groups);
                return userGroups.includes(groupIdOrName);
            }
        } else {
            return false;
        }
    }

    isAdmin() {
        return this.hasGroup(USER_GROUP_ADMIN);
    }

    /** Info **/
    async info() {
        return this.apiCall("info");
    }

    /** UserAPI **/
    async login(username, password, rememberMe=false) {
        return this.apiCall("user/login", { username: username, password: password, stayLoggedIn: rememberMe })
    }

    async fetchUser() {
        let response = await fetch("/api/user/info");
        let data = await response.json();
        if (data) {
            this.loggedIn = data["loggedIn"];
            this.permissions = data["permissions"] ? data["permissions"].map(s => s.toLowerCase()) : [];
            if (this.loggedIn) {
                this.session = data["session"];
                this.user = data["user"];
            } else {
                this.session = null;
                this.user = null;
            }
        }
        return data;
    }

    async editUser(id, username, email, password, groups, confirmed) {
        return this.apiCall("user/edit", {
            id: id, username: username, email: email,
            password: password, groups: groups, confirmed: confirmed
        });
    }

    async logout() {
        const res = await this.apiCall("user/logout");
        if (res.success) {
            this.loggedIn = false;
            this.permissions = [];
            this.session = null;
            this.user = null;
        }

        return res;
    }

    async getUser(id) {
        return this.apiCall("user/get", { id: id });
    }

    async deleteUser(id) {
        return this.apiCall("user/delete", { id: id });
    }

    async fetchUsers(pageNum = 1, count = 20, orderBy = 'id', sortOrder = 'asc') {
        return this.apiCall("user/fetch", { page: pageNum, count: count, orderBy: orderBy, sortOrder: sortOrder });
    }

    async fetchGroups(pageNum = 1, count = 20, orderBy = 'id', sortOrder = 'asc') {
        return this.apiCall("groups/fetch", { page: pageNum, count: count, orderBy: orderBy, sortOrder: sortOrder });
    }

    async getGroup(id) {
        return this.apiCall("groups/get", { id: id });
    }

    async inviteUser(username, email) {
        return this.apiCall("user/invite", { username: username, email: email });
    }

    async createUser(username, email, password, confirmPassword) {
        return this.apiCall("user/create", { username: username, email: email, password: password, confirmPassword: confirmPassword });
    }

    /** Stats **/
    async getStats() {
        return this.apiCall("stats");
    }

    /** RoutesAPI **/
    async getRoutes() {
        return this.apiCall("routes/fetch");
    }

    async saveRoutes(routes) {
        return this.apiCall("routes/save", { routes: routes });
    }

    /** GroupAPI **/
    async createGroup(name, color) {
        return this.apiCall("groups/create", { name: name, color: color });
    }

    async deleteGroup(id) {
        return this.apiCall("groups/delete", { id: id });
    }

    /** SettingsAPI **/
    async getSettings(key = "") {
        return this.apiCall("settings/get", { key: key });
    }

    async saveSettings(settings) {
        return this.apiCall("settings/set", { settings: settings });
    }

    /** MailAPI **/
    async sendTestMail(receiver) {
        return this.apiCall("mail/test", { receiver: receiver });
    }

    /** PermissionAPI **/
    async fetchPermissions() {
        return this.apiCall("permission/fetch");
    }

    async savePermissions(permissions) {
        return this.apiCall("permission/save", { permissions: permissions });
    }

    /** VisitorsAPI **/
    async getVisitors(type, date) {
        return this.apiCall("visitors/stats", { type: type, date: date });
    }

    /** LanguageAPI **/
    async getLanguages() {
        return this.apiCall("language/get");
    }

    async setLanguage(params) {
        return await this.apiCall("language/set", params);
    }

    async getLanguageEntries(modules, code=null, useCache=false) {
        if (!Array.isArray(modules)) {
            modules = [modules];
        }

        return this.apiCall("language/getEntries", {code: code, modules: modules});
    }

    /** ApiKeyAPI **/
    // API-Key API
    async getApiKeys(showActiveOnly = false) {
        return this.apiCall("apiKey/fetch", { showActiveOnly: showActiveOnly });
    }

    async createApiKey() {
        return this.apiCall("apiKey/create");
    }

    async revokeKey(id) {
        return this.apiCall("apiKey/revoke", { id: id });
    }
};