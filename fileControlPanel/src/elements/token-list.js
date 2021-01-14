import * as React from "react";
import Icon from "./icon";
import moment from "moment";
import {Popup} from "./popup";
import Alert from "./alert";
import {useState} from "react";

export function TokenList(props) {

    let api = props.api;
    let selectedFiles = props.selectedFiles || [];
    let directories   = props.directories || {};

    let [tokens, setTokens] = useState(null);
    let [alerts, setAlerts] = useState([]);
    let [hideRevoked, setHideRevoked] = useState(true);
    let [popup, setPopup] = useState({
        tokenType: "download",
        maxFiles: 0,
        maxSize: 0,
        extensions: "",
        durability: 24 * 60 * 2,
        visible: false,
        directory: 0
    });

    function fetchTokens() {
        api.listTokens().then((res) => {
            if (res) {
                setTokens(res.tokens);
            } else {
                pushAlert(res, "Error fetching tokens");
            }
        });
    }


    let rows = [];
    if (tokens === null) {
        fetchTokens();
    } else {
        for (const token of tokens) {
            const validUntil = token.valid_until;
            const revoked = validUntil !== null && moment(validUntil).isSameOrBefore(new Date());
            if (revoked && hideRevoked) {
                continue;
            }

            const timeStr = (validUntil === null ? "Forever" : moment(validUntil).format("Do MMM YYYY, HH:mm"));

            rows.push(
                <tr key={"token-" + token.uid} className={revoked ? "token-revoked" : ""}>
                    <td>{token.token}</td>
                    <td>{token.type}</td>
                    <td>{timeStr}</td>
                    <td>
                        <Icon icon={"times"} className={"clickable text-" + (revoked ? "secondary" : "danger")}
                              onClick={() => (revoked ? null : onRevokeToken(token.token))}
                              disabled={revoked}/>
                        <Icon icon={"save"} className={"clickable text-" + (revoked ? "secondary" : "info")}
                              onClick={() => (revoked ? null : onCopyToken(token.token))}
                              disabled={revoked}/>
                    </td>
                </tr>
            );
        }
    }

    let alertElements = [];
    for (let i = 0; i < alerts.length; i++) {
        const alert = alerts[i];
        alertElements.push(
            <Alert key={"alert-" + i} {...alert} onClose={() => removeAlert(i)}/>
        );
    }

    let options = [];
    for (const [uid, dir] of Object.entries(directories)) {
        options.push(
            <option key={"option-" + dir} value={uid}>{dir}</option>
        );
    }

    return <>
        <h4>
            <Icon icon={"sync"} className={"mx-3 clickable small"} onClick={fetchTokens}/>
            Tokens
        </h4>
        <div className={"form-check p-3 ml-3"}>
            <input type={"checkbox"} checked={hideRevoked} name={"hide-revoked"}
                   className={"form-check-input"} style={{marginTop: "0.2rem"}}
                   onChange={(e) => setHideRevoked(e.target.checked)}/>
            <label htmlFor={"hide-revoked"} className={"form-check-label pl-2"}>Hide revoked</label>
        </div>
        <table className={"table token-table"}>
            <thead>
            <tr>
                <th>Token</th>
                <th>Type</th>
                <th>Valid Until</th>
                <th/>
            </tr>
            </thead>
            <tbody>
            {rows.length > 0 ? rows :
                <tr>
                    <td colSpan={4} className={"text-center text-black-50"}>
                        No active tokens connected with this account
                    </td>
                </tr>
            }
            </tbody>
        </table>
        <div>
            <button type={"button"} className={"btn btn-success m-2"} onClick={onPopupOpen}>
                <Icon icon={"plus"} className={"mr-1"}/>
                Create Token
            </button>
        </div>
        <div>
            {alertElements}
        </div>
        <Popup title={"Create Token"} visible={popup.visible} buttons={["Ok", "Cancel"]}
               onClose={onPopupClose} onClick={onPopupButton}>
            <div className={"form-group"}>
                <label>Token Durability in minutes (0 = forever):</label>
                <input type={"number"} min={0} className={"form-control"}
                       value={popup.durability} onChange={(e) => onPopupChange(e, "durability")}/>
            </div>
            <div className="form-group">
                <label>Token Type:</label>
                <select value={popup.tokenType} className={"form-control"}
                        onChange={(e) => onPopupChange(e, "tokenType")}>
                    <option value={"upload"}>Upload</option>
                    <option value={"download"}>Download</option>
                </select>
            </div>
            {popup.tokenType === "upload" ?
                <>
                    <div className={"form-group"}>
                        <label>Destination Directory:</label>
                        <select value={popup.directory} className={"form-control"}
                                onChange={(e) => onPopupChange(e, "directory")}>
                            { options }
                        </select>
                    </div>
                    <b>Upload Restrictions:</b>
                    <div className={"form-group"}>
                        <label>Max. Files (0 = unlimited):</label>
                        <input type={"number"} min={0} max={25} className={"form-control"}
                               value={popup.maxFiles}
                               onChange={(e) => onPopupChange(e, "maxFiles")}/>
                    </div>
                    <div className={"form-group"}>
                        <label>Max. Size per file in MB (0 = unlimited):</label>
                        <input type={"number"} min={0} max={10} className={"form-control"}
                               value={popup.maxSize} onChange={(e) => onPopupChange(e, "maxSize")}/>
                    </div>
                    <div className={"form-group"}>
                        <label>Allowed Extensions:</label>
                        <input type={"text"} placeholder={"(no restrictions)"} maxLength={256}
                               className={"form-control"}
                               value={popup.extensions}
                               onChange={(e) => onPopupChange(e, "extensions")}/>
                    </div>
                </> :
                <></>
            }
        </Popup>
    </>;

    function pushAlert(res, title) {
        let newAlerts = alerts.slice();
        newAlerts.push({type: "danger", message: res.msg, title: title});
        setAlerts(newAlerts);
    }

    function removeAlert(i) {
        if (i >= 0 && i < alerts.length) {
            let newAlerts = alerts.slice();
            newAlerts.splice(i, 1);
            setAlerts(newAlerts);
        }
    }

    function onRevokeToken(token) {
        api.revokeToken(token).then((res) => {
            if (res.success) {
                let newTokens = tokens.slice();
                for (const tokenObj of newTokens) {
                    if (tokenObj.token === token) {
                        tokenObj.valid_until = moment();
                        break;
                    }
                }
                setTokens(newTokens);
            } else {
                pushAlert(res, "Error revoking token");
            }
        });
    }

    function onPopupOpen() {
        setPopup({...popup, visible: true});
    }

    function onPopupClose() {
        setPopup({...popup, visible: false});
    }

    function onPopupChange(e, key) {
        setPopup({...popup, [key]: e.target.value});
    }

    function onPopupButton(btn) {

        if (btn === "Ok") {
            let durability = popup.durability;
            let validUntil = (durability === 0 ? null : moment().add(durability, "hours").format("YYYY-MM-DD HH:mm:ss"));
            if (popup.tokenType === "download") {
                api.createDownloadToken(durability, selectedFiles).then((res) => {
                    if (!res.success) {
                        pushAlert(res, "Error creating token");
                    } else {
                        let newTokens = tokens.slice();
                        newTokens.push({token: res.token, valid_until: validUntil, type: "download"});
                        setTokens(newTokens);
                    }
                });
            } else if (popup.tokenType === "upload") {
                let parentId = popup.directory === 0 ? null : popup.directory;
                api.createUploadToken(durability, parentId, popup.maxFiles, popup.maxSize, popup.extensions).then((res) => {
                    if (!res.success) {
                        pushAlert(res, "Error creating token");
                    } else {
                        let newTokens = tokens.slice();
                        newTokens.push({uid: res.tokenId, token: res.token, valid_until: validUntil, type: "upload"});
                        setTokens(newTokens);
                    }
                });
            }
        }

        onPopupClose();
    }

    function onCopyToken(token) {
        let url = window.location.href;
        if (!url.endsWith("/")) url += "/";
        url += token;
        navigator.clipboard.writeText(url);
    }
}