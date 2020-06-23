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

    async logout() {
        return this.apiCall("user/logout");
    }

    async getNotifications() {
        return this.apiCall("notifications/fetch");
    }

    async getUser(id) {
        return this.apiCall("user/get", { id: id });
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
        return this.apiCall("routes/save", { "routes": routes });
    }
};