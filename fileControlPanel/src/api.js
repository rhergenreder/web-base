import 'babel-polyfill';
import axios from "axios";

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
        let response = await axios.post("/api/" + method, params);
        return response.data;
    }

    async fetchUser() {
        let response = await axios.get("/api/user/info");
        let data = response.data;
        this.user = data["user"];
        this.loggedIn = data["loggedIn"];
        return data && data["success"] && data["loggedIn"];
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

    delete(id, token=null) {
        return this.apiCall("file/delete", { id: id, token: token });
    }

    revokeToken(token) {
        return this.apiCall("file/revokeToken", { token: token });
    }

    createDownloadToken(durability, files) {
        return this.apiCall("file/createDownloadToken", { files: files, durability: durability });
    }

    createUploadToken(durability, parentId=null, maxFiles=0, maxSize=0, extensions = "") {
        return this.apiCall("file/createUploadToken", { parentId: parentId, durability: durability, maxFiles: maxFiles, maxSize: maxSize, extensions: extensions });
    }

    createDirectory(name, parentId = null) {
        return this.apiCall("file/createDirectory", { name: name, parentId: parentId });
    }

    getRestrictions() {
        return this.apiCall("file/getRestrictions");
    }

    async upload(file, token = null, parentId = null, cancelToken = null, onUploadProgress = null) {
        const csrf_token = this.csrfToken();

        const fd = new FormData();
        fd.append("file", file);
        if (csrf_token) fd.append("csrf_token", csrf_token);
        if (token) fd.append("token", token);
        if (parentId) fd.append("parentId", parentId);

        let response = await axios.post('/api/file/upload', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: onUploadProgress || function () { },
            cancelToken : cancelToken.token
        });

        return response.data;
    }
};