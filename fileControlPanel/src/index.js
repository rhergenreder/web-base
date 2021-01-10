import React from 'react';
import ReactDOM from 'react-dom';
import API from "./api";
import Icon from "./elements/icon";
import {FileBrowser} from "./elements/file-browser";
import {TokenList} from "./elements/token-list";

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
            files: [],
        };
    }

    onValidateToken() {
        this.setState({ ...this.state, validatingToken: true, errorMessage: "" })
        this.api.validateToken(this.state.token.value).then((res) => {
            if (res.success) {
                this.setState({ ...this.state, validatingToken: false,
                    token: {
                        ...this.state.token,
                        valid: true,
                        validUntil: res.token.valid_util,
                        type: res.token.type
                    },
                    files: res.files
                });
            } else {
                this.setState({ ...this.state, validatingToken: false, errorMessage: res.msg });
            }
        });
    }

    onUpdateToken(e) {
        this.setState({ ...this.state, token: { ...this.state.token, value: e.target.value } });
    }

    render() {
        const self = this;
        const errorMessageShown = !!this.state.errorMessage;

        if (!this.state.loaded) {
            this.api.fetchUser().then((isLoggedIn) => {
                console.log(`api.fetchUser => ${isLoggedIn}`);
                if (isLoggedIn) {
                    this.api.listFiles().then((res) => {
                        console.log(`api.listFiles => ${res.success}`);
                        this.setState({ ...this.state, loaded: true, user: this.api.user, files: res.files });
                    });
                } else {
                    this.setState({ ...this.state, loaded: true, user: this.api.user });
                }
            });
            return <>Loading… <Icon icon={"spinner"} /></>;
        } else if (this.api.loggedIn || this.state.token.valid) {
            let tokenList = (this.api.loggedIn) ?
                <div className={"row"}>
                    <div className={"col-lg-6 col-md-8 col-sm-10 col-xs-12 mx-auto"}>
                        <TokenList api={this.api} />
                    </div>
                </div> :
                <></>;

            return <div className={"container mt-4"}>
                <div className={"row"}>
                    <div className={"col-lg-6 col-md-8 col-sm-10 col-xs-12 mx-auto"}>
                        <h2>File Control Panel</h2>
                        <FileBrowser files={this.state.files}/>
                    </div>
                </div>
                { tokenList }
            </div>;
        } else {
            return <div className={"container mt-4"}>
                <div className={"row"}>
                    <div className={"col-lg-6 col-md-8 col-sm-10 col-xs-12 mx-auto"}>
                        <h2>File Control Panel</h2>
                        <form onSubmit={(e) => e.preventDefault()}>
                            <label htmlFor={"token"}>Enter a file token to download or upload files</label>
                            <input type={"text"} className={"form-control"} name={"token"} placeholder={"Enter token…"} maxLength={36}
                                   value={this.state.token.value} onChange={(e) => self.onUpdateToken(e)}/>
                            <button className={"btn btn-success mt-2"} onClick={this.onValidateToken.bind(this)} disabled={this.state.validatingToken}>
                                { this.state.validatingToken ? <>Validating… <Icon icon={"spinner"}/></> : "Submit" }
                            </button>
                        </form>
                        <div className={"alert alert-danger mt-2"} hidden={!errorMessageShown}>
                            { this.state.errorMessage }
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
