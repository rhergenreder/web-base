import 'babel-polyfill';

export default class API {

    constructor() {
        this.loggedIn = false;
        this.user = { };
    }

    csrfToken() {
        console.log(this.loggedIn);
        console.log(this.user);
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

    async upload(files, token = null, parentId = null) {
        const csrf_token = this.csrfToken();

        const fd = new FormData();
        for (let i = 0; i < files.length; i++) {
            fd.append('file' + i, files[i]);
        }

        if (csrf_token) fd.append("csrf_token", csrf_token);
        if (token) fd.append("token", token);
        if (parentId) fd.append("parentId", parentId);

        // send `POST` request
        let response = await fetch('/api/file/upload', {
            method: 'POST',
            body: fd
        });

        return response.json();
    }
};