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
};