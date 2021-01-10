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

        const csrf_token = this.csrfToken();
        if (csrf_token) params.csrf_token = csrf_token;
        let response = await fetch("/api/" + method, {
            method: 'post',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(params)
        });

        return await response.json();
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

    validateToken(token) {
        return this.apiCall("file/validateToken", { token: token });
    }

    listFiles() {
        return this.apiCall("file/listFiles");
    }

    listTokens() {
        return this.apiCall("file/listTokens");
    }

    delete(id) {
        return this.apiCall("file/delete", { id: id })
    }
};