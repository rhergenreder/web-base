import 'babel-polyfill';

export default class API {
    constructor() {
        this.user = {};
        this.baseUrl = "http://localhost"
    }

    async fetchUser() {
        let response = await fetch(this.baseUrl + "/api/user/fetch");
        let data = await response.json()
        this.user = data["users"][0];
        return data && data.success && data.hasOwnProperty("logoutIn");
    }

    async logout() {
        let response = await fetch(this.baseUrl + "/api/user/logout");
        return await response.json();
    }
};