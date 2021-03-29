import React from 'react';
import ReactDOM from 'react-dom';
import API from "./api";
import Icon from "./elements/icon";
import {FileBrowser} from "./elements/file-browser";
import {TokenList} from "./elements/token-list";
import {Link} from "react-router-dom";

class FileControlPanel extends React.Component {

    constructor(props) {
        super(props);
        this.api = new API();
        this.state = {
            loaded: false,
            validatingToken: false,
            errorMessage: "",
            user: { },
            token: { valid: false, value: "", validUntil: null, type: null },
            files: {}
        };
    }

    onFetchFiles(files) {
        this.setState({ ...this.state, files: files });
    }

    getDirectories(prefix = "/", items = null) {
        let directories = { }
        items = items || this.state.files;

        if (prefix === "/") {
            directories[0] = "/";
        }

        for(const fileItem of Object.values(items)) {
            if (fileItem.isDirectory) {
                let path = prefix + (prefix.length > 1 ? "/" : "") + fileItem.name;
                directories[fileItem.uid] = path;
                directories = Object.assign(directories, {...this.getDirectories(path, fileItem.items)});
            }
        }
        return directories;
    }

    getSelectedIds(items = null, recursive = true) {
        let ids = [];
        items = items || this.state.files;
        for (const fileItem of Object.values(items)) {
            if (fileItem.selected) {
                ids.push(fileItem.uid);
            }
            if (recursive && fileItem.isDirectory) {
                ids.push(...this.getSelectedIds(fileItem.items));
            }
        }

        return ids;
    }

    onSelectAll(selected, items) {
        for (const fileElement of Object.values(items)) {
            fileElement.selected = selected;
            if (fileElement.isDirectory) {
                this.onSelectAll(selected, fileElement.items);
            }
        }
    }

    onSelectFile(e, uid, items=null) {

        let found = false;
        let updatedFiles = (items === null) ? {...this.state.files} : items;
        if (updatedFiles.hasOwnProperty(uid)) {
            let fileElement = updatedFiles[uid];
            found = true;
            fileElement.selected = e.target.checked;
            if (fileElement.isDirectory) {
                this.onSelectAll(fileElement.selected, fileElement.items);
            }
        } else {
            for (const fileElement of Object.values(updatedFiles)) {
                if (fileElement.isDirectory) {
                    if (this.onSelectFile(e, uid, fileElement.items)) {
                        if (!e.target.checked) {
                            fileElement.selected = false;
                        } else if (this.getSelectedIds(fileElement.items, false).length === Object.values(fileElement.items).length) {
                            fileElement.selected = true;
                        }
                        found = true;
                        break;
                    }
                }
            }
        }

        if (items === null) {
            this.setState({
                ...this.state,
                files: updatedFiles
            });
        }

        return found;
    }

    onValidateToken(token = null) {
        if (token === null) {
            this.setState({ ...this.state, validatingToken: true, errorMessage: "" });
            token = this.state.token.value;
        }

        this.api.validateToken(token).then((res) => {
            let newState = { ...this.state, loaded: true, validatingToken: false };
            if (res.success) {
                newState.token = { ...this.state.token, valid: true, validUntil: res.token.valid_until, type: res.token.type };
                if (!newState.token.value) {
                    newState.token.value = token;
                }
                newState.files = res.files;
            } else {
                newState.token.value = (newState.token.value ? "" : token);
                newState.errorMessage = res.msg;
            }

            this.setState(newState);
        });
    }

    onUpdateToken(e) {
        this.setState({ ...this.state, token: { ...this.state.token, value: e.target.value } });
    }

    render() {
        const self = this;
        const errorMessageShown = !!this.state.errorMessage;

        // still loading
        if (!this.state.loaded) {

            let checkUser = true;
            let pathName = window.location.pathname;
            if (pathName.startsWith("/files")) {
                pathName = pathName.substr("/files".length);
            }

            if (pathName.length > 1) {
                let end = (pathName.endsWith("/") ? pathName.length - 2 : pathName.length - 1);
                let start = (pathName.startsWith("/files/") ? ("/files/").length : 1);
                let token = pathName.substr(start, end);
                if (token) {
                    this.onValidateToken(token);
                    checkUser = false;
                }
            }

            if (checkUser) {
                this.api.fetchUser().then((isLoggedIn) => {
                    if (isLoggedIn) {
                        this.api.listFiles().then((res) => {
                            this.setState({ ...this.state, loaded: true, user: this.api.user, files: res.files });
                        });
                    } else {
                        this.setState({ ...this.state, loaded: true, user: this.api.user });
                    }
                });
            }

            return <>Loading… <Icon icon={"spinner"} /></>;
        }
        // access granted
        else if (this.api.loggedIn || this.state.token.valid) {
            let selectedIds = this.getSelectedIds();
            let directories = this.getDirectories();
            let tokenList = (this.api.loggedIn) ?
                <div className={"row"}>
                    <div className={"col-lg-8 col-md-10 col-sm-12 mx-auto"}>
                        <TokenList api={this.api} selectedFiles={selectedIds} directories={directories} />
                    </div>
                </div> :
                <></>;

            return <>
                    <div className={"container mt-4"}>
                    <div className={"row"}>
                        <div className={"col-lg-8 col-md-10 col-sm-12 mx-auto"}>
                            <h2>File Control Panel</h2>
                            <FileBrowser files={this.state.files} token={this.state.token} api={this.api} directories={directories}
                                         onSelectFile={this.onSelectFile.bind(this)}
                                         onFetchFiles={this.onFetchFiles.bind(this)}/>
                        </div>
                    </div>
                    { tokenList }
                </div>
            </>;
        } else {
            return <div className={"container mt-4"}>
                <div className={"row"}>
                    <div className={"col-lg-8 col-md-10 col-sm-12 mx-auto"}>
                        <h2>File Control Panel</h2>
                        <form onSubmit={(e) => e.preventDefault()}>
                            <label htmlFor={"token"}>Enter a file token to download or upload files</label>
                            <input type={"text"} className={"form-control"} name={"token"} placeholder={"Enter token…"} maxLength={36}
                                   value={this.state.token.value} onChange={(e) => self.onUpdateToken(e)}/>
                            <button className={"btn btn-success mt-2"} onClick={() => this.onValidateToken()} disabled={this.state.validatingToken}>
                                { this.state.validatingToken ? <>Validating… <Icon icon={"spinner"}/></> : "Submit" }
                            </button>
                        </form>
                        <div className={"alert alert-danger mt-2"} hidden={!errorMessageShown}>
                            { this.state.errorMessage }
                        </div>
                        <div className={"mt-3"}>
                            Or either <a href={"/admin"}>login</a> to access the file control panel.
                        </div>
                    </div>
                </div>
            </div>;
        }
    }
}

ReactDOM.render(
    <FileControlPanel />,
    document.getElementById('root')
);
