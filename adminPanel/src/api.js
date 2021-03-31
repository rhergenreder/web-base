import 'babel-polyfill';

export default class API {
    constructor() {
        this.loggedIn = false;
        this.user = { };
    }

    csrfToken() {
        return this.loggedIn ? this.user.session.csrf_token : null;
    }

    async apiCall(method, params) {
        params = params || { };
        params.csrf_token = this.csrfToken();
        let response = await fetch("/api/" + method, {
            method: 'post',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(params)
        });

        let res = await response.json();
        if (!res.success && res.msg === "You are not logged in.") {
            document.location.reload();
        }

        return res;
    }

    async fetchUser() {
        let response = await fetch("/api/user/info");
        let data = await response.json();
        this.user = data["user"];
        this.loggedIn = data["loggedIn"];
        return data && data.success && data.loggedIn;
    }

    async editUser(id, username, email, password, groups, confirmed) {
        return this.apiCall("user/edit", { id: id, username: username, email: email, password: password, groups: groups, confirmed: confirmed });
    }

    async logout() {
        return this.apiCall("user/logout");
    }

    async getNotifications(onlyNew = true) {
        return this.apiCall("notifications/fetch", { new: onlyNew });
    }

    async markNotificationsSeen() {
        return this.apiCall("notifications/seen");
    }

    async getUser(id) {
        return this.apiCall("user/get", { id: id });
    }

    async deleteUser(id) {
        return this.apiCall("user/delete", { id: id });
    }

    async fetchUsers(pageNum = 1, count = 20) {
        return this.apiCall("user/fetch", { page: pageNum, count: count });
    }

    async fetchGroups(pageNum = 1, count = 20) {
        return this.apiCall("groups/fetch", { page: pageNum, count: count });
    }

    async inviteUser(username, email) {
        return this.apiCall("user/invite", { username: username, email: email });
    }

    async createUser(username, email, password, confirmPassword) {
        return this.apiCall("user/create", { username: username, email: email, password: password, confirmPassword: confirmPassword });
    }

    async getStats() {
        return this.apiCall("stats");
    }

    async getRoutes() {
        return this.apiCall("routes/fetch");
    }

    async saveRoutes(routes) {
        return this.apiCall("routes/save", { routes: routes });
    }

    async createGroup(name, color) {
        return this.apiCall("groups/create", { name: name, color: color });
    }

    async deleteGroup(id) {
        return this.apiCall("groups/delete", { uid: id });
    }

    async getSettings(key = "") {
        return this.apiCall("settings/get", { key: key });
    }

    async saveSettings(settings) {
        return this.apiCall("settings/set", { settings: settings });
    }

    async sendTestMail(receiver) {
        return this.apiCall("mail/test", { receiver: receiver });
    }

    async fetchPermissions() {
        return this.apiCall("permission/fetch");
    }

    async savePermissions(permissions) {
        return this.apiCall("permission/save", { permissions: permissions });
    }

    async getVisitors(type, date) {
        return this.apiCall("visitors/stats", { type: type, date: date });
    }
};