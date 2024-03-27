import {USER_GROUP_ADMIN} from "./constants";
import {createDownload, isInt} from "./util";

Date.prototype.toJSON = function() {
    return Math.round(this.getTime() / 1000);
};

export default class API {
    constructor() {
        this.loggedIn = false;
        this.user = null;
        this.session = null;
        this.language = { id: 1, code: "en_US", shortCode: "en", name: "English (US)" };
        this.permissions = [];
    }

    csrfToken() {
        return this.loggedIn ? this.session.csrfToken : null;
    }

    async apiCall(method, params, expectBinary=false) {
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
        if (response.headers.has("content-disposition")) {
            let contentDisposition = response.headers.get("content-disposition");
            if (contentDisposition.toLowerCase().startsWith("attachment;")) {
                let fileName = /filename="?([^"]*)"?/;
                let blob = await response.blob();
                createDownload(fileName.exec(contentDisposition)[1], blob);
                return { success: true, msg: "" };
            }
        }

        let res = await response.json();
        if (!res.success && res.msg === "You are not logged in.") {
            this.loggedIn = false;
            this.user = null;
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
        let res = await this.apiCall("user/login", { username: username, password: password, stayLoggedIn: rememberMe });
        if (res.success) {
            this.loggedIn = true;
            this.session = res.session;
            this.user = res.user;
        }

        return res;
    }

    async fetchUser() {
        let res = await this.apiCall("user/info");
        if (res.success) {
            this.loggedIn = res.loggedIn;
            this.language = res.language;
            this.permissions = (res.permissions || []).map(s => s.toLowerCase());
            if (this.loggedIn) {
                this.session = res.session;
                this.user = res.user;
            } else {
                this.session = null;
                this.user = null;
            }
        }
        return res;
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

    async updateProfile(username=null, fullName=null, password=null, confirmPassword = null, oldPassword = null) {
        let res = await this.apiCall("user/updateProfile", { username: username, fullName: fullName,
            password: password, confirmPassword: confirmPassword, oldPassword: oldPassword });

        if (res.success) {
            if (username !== null) {
                this.user.name = username;
            }

            if (fullName !== null) {
                this.user.fullName = fullName;
            }
        }

        return res;
    }

    async uploadPicture(file, scale=1.0) {
        const formData = new FormData();
        formData.append("scale", scale);
        formData.append("picture", file, file.name);
        let res = await this.apiCall("user/uploadPicture", formData);
        if (res.success) {
            this.user.profilePicture = res.profilePicture;
        }

        return res;
    }

    async removePicture() {
        let res = await this.apiCall("user/removePicture");
        if (res.success) {
            this.user.profilePicture = null;
        }

        return res;
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

    async updatePermission(method, groups, description = null) {
        return this.apiCall("permission/update", { method: method, groups: groups, description: description });
    }

    async deletePermission(method) {
        return this.apiCall("permission/delete", { method: method });
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
        let res = await this.apiCall("language/set", params);
        if (res.success) {
            this.language = res.language;
        }

        return res;
    }

    async getLanguageEntries(modules, code=null, useCache=false) {
        if (!Array.isArray(modules)) {
            modules = [modules];
        }

        return this.apiCall("language/getEntries", {code: code, modules: modules});
    }

    /** ApiKeyAPI **/
    async getApiKeys(showActiveOnly = false, page = 1, count = 25, orderBy = "validUntil", sortOrder = "desc") {
        return this.apiCall("apiKey/fetch", { showActiveOnly: showActiveOnly, page: page, count: count, orderBy: orderBy, sortOrder: sortOrder });
    }

    async createApiKey() {
        return this.apiCall("apiKey/create");
    }

    async revokeKey(id) {
        return this.apiCall("apiKey/revoke", { id: id });
    }

    /** 2FA API **/
    async confirmTOTP(code) {
        let res = await this.apiCall("tfa/confirmTotp", { code: code });
        if (res.success) {
            this.user.twoFactorToken = { type: "totp", confirmed: true };
        }

        return res;
    }

    async remove2FA(password) {
        let res = await this.apiCall("tfa/remove", { password: password });
        if (res.success) {
            this.user.twoFactorToken = null;
        }

        return res;
    }

    async verifyTotp2FA(code) {
        return this.apiCall("tfa/verifyTotp", { code: code });
    }

    async verifyKey2FA(credentialID, clientDataJSON, authData, signature) {
        return this.apiCall("tfa/verifyKey", { credentialID: credentialID, clientDataJSON: clientDataJSON, authData: authData, signature: signature })
    }

    async register2FA(clientDataJSON = null, attestationObject = null) {
        let res = await this.apiCall("tfa/registerKey", { clientDataJSON: clientDataJSON, attestationObject: attestationObject });
        if (res.success && res.twoFactorToken) {
            this.user.twoFactorToken = res.twoFactorToken;
        }

        return res;
    }

    /** GPG API **/
    async uploadGPG(pubkey) {
        let res = await this.apiCall("user/importGPG", { pubkey: pubkey });
        if (res.success) {
            this.user.gpgKey = res.gpgKey;
        }

        return res;
    }

    async confirmGpgToken(token) {
        let res = await this.apiCall("user/confirmGPG", { token: token });
        if (res.success) {
            this.user.gpgKey.confirmed = true;
        }

        return res;
    }

    async removeGPG(password) {
        let res = await this.apiCall("user/removeGPG", { password: password });
        if (res.success) {
            this.user.gpgKey = null;
        }

        return res;
    }

    async downloadGPG(userId) {
        return this.apiCall("user/downloadGPG", { id: userId }, true);
    }

    /** Log API **/
    async fetchLogEntries(pageNum = 1, count = 20, orderBy = 'id', sortOrder = 'asc',
                          severity = "debug", since = null, query = "") {
        return this.apiCall("logs/get", {
            page: pageNum, count: count, orderBy: orderBy, sortOrder: sortOrder,
            since: since, severity: severity, query: query
        });
    }
};