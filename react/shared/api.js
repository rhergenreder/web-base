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

    async apiCall(method, params = {}, expectBinary=false) {
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
        if (!res.success) {
            if (res.loggedIn === false) {
                this.loggedIn = false;
                this.user = null;
                this.session = null;
            } else if (res.twoFactorToken === true) {
                this.user.twoFactorToken = res.twoFactorToken;
            }
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

    async inviteUser(username, email) {
        return this.apiCall("user/invite", { username: username, email: email });
    }

    async createUser(username, email, password, confirmPassword) {
        return this.apiCall("user/create", { username: username, email: email, password: password, confirmPassword: confirmPassword });
    }

    async searchUser(query) {
        return this.apiCall("user/search", { query : query });
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

    /** Groups API **/
    async fetchGroups(pageNum = 1, count = 20, orderBy = 'id', sortOrder = 'asc') {
        return this.apiCall("groups/fetch", { page: pageNum, count: count, orderBy: orderBy, sortOrder: sortOrder });
    }

    async fetchGroupMembers(groupId, pageNum = 1, count = 20, orderBy = 'id', sortOrder = 'asc') {
        return this.apiCall("groups/getMembers", { id: groupId, page: pageNum, count: count, orderBy: orderBy, sortOrder: sortOrder });
    }

    async removeGroupMember (groupId, userId) {
        return this.apiCall("groups/removeMember", { id: groupId, userId: userId });
    }

    async addGroupMember (groupId, userId) {
        return this.apiCall("groups/addMember", { id: groupId, userId: userId });
    }

    async getGroup(id) {
        return this.apiCall("groups/get", { id: id });
    }

    async createGroup(name, color) {
        return this.apiCall("groups/create", { name: name, color: color });
    }

    async updateGroup(id, name, color) {
        return this.apiCall("groups/update", { id: id, name: name, color: color });
    }

    async deleteGroup(id) {
        return this.apiCall("groups/delete", { id: id });
    }

    /** Stats **/
    async getStats() {
        return this.apiCall("stats");
    }

    /** RoutesAPI **/
    async fetchRoutes() {
        return this.apiCall("routes/fetch");
    }

    async getRoute(id) {
        return this.apiCall("routes/get", { id: id });
    }

    async enableRoute(id) {
        return this.apiCall("routes/enable", { id: id });
    }

    async disableRoute(id) {
        return this.apiCall("routes/disable", { id: id });
    }

    async deleteRoute(id) {
        return this.apiCall("routes/remove", { id: id });
    }

    async regenerateRouterCache() {
        return this.apiCall("routes/generateCache");
    }

    async testRoute(pattern, path, exact = true) {
        return this.apiCall("routes/check", { pattern: pattern, path: path, exact: exact });
    }

    async addRoute(pattern, type, target, extra, exact, active) {
        return this.apiCall("routes/add", { pattern, type, target, extra, exact, active });
    }

    async updateRoute(id, pattern, type, target, extra, exact, active) {
        return this.apiCall("routes/update", { id, pattern, type, target, extra, exact, active });
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

    async getLanguageEntries(modules, code=null, compression=null) {
        if (!Array.isArray(modules)) {
            modules = [modules];
        }

        return this.apiCall("language/getEntries", {code: code, modules: modules, compression: compression});
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
        let res = await this.apiCall("gpgKey/import", { pubkey: pubkey });
        if (res.success) {
            this.user.gpgKey = res.gpgKey;
        }

        return res;
    }

    async confirmGpgToken(token) {
        let res = await this.apiCall("gpgKey/confirm", { token: token });
        if (res.success) {
            this.user.gpgKey.confirmed = true;
        }

        return res;
    }

    async removeGPG(password) {
        let res = await this.apiCall("gpgKey/remove", { password: password });
        if (res.success) {
            this.user.gpgKey = null;
        }

        return res;
    }

    async downloadGPG(userId) {
        return this.apiCall("gpgKey/download", { id: userId }, true);
    }

    /** Log API **/
    async fetchLogEntries(pageNum = 1, count = 20, orderBy = 'id', sortOrder = 'asc',
                          severity = "debug", since = null, query = "") {
        return this.apiCall("logs/get", {
            page: pageNum, count: count, orderBy: orderBy, sortOrder: sortOrder,
            since: since, severity: severity, query: query
        });
    }

    /** Redis **/
    async testRedis() {
        return this.apiCall("testRedis");
    }
};