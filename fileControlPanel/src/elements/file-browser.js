import * as React from "react";
import "./file-browser.css";
import Dropzone from "react-dropzone";
import Icon from "./icon";
import Alert from "./alert";
import {Popup} from "./popup";
import {useEffect, useState} from "react";
import axios from "axios";

export function FileBrowser(props) {

    let files = props.files || {};
    let api = props.api;
    let tokenObj = props.token || {valid: false};
    let onSelectFile = props.onSelectFile || function () { };
    let onFetchFiles = props.onFetchFiles || function () { };
    let directories = props.directories || {};
    let restrictions = props.restrictions || {maxFiles: 0, maxSize: 0, extensions: ""};

    let [popup, setPopup] = useState({ visible: false, name: "", directory: 0, target: null, type: "createDirectory" });
    let [alerts, setAlerts] = useState([]);
    let [filesToUpload, setFilesToUpload] = useState([]);

    function canUpload() {
        return api.loggedIn || (tokenObj.valid && tokenObj.type === "upload");
    }

    function svgMiddle(key, scale = 1.0) {
        let width = 48 * scale;
        let height = 64 * scale;

        return <svg key={key} width={width} height={height} xmlns="http://www.w3.org/2000/svg">
            <g>
                <line y2="0" x2={width / 2} y1={height} x1={width / 2} strokeWidth="1.5" stroke="#000" fill="none"/>
                <line y2={height / 2} x2={width} y1={height / 2} x1={width / 2} strokeWidth="1.5" stroke="#000"
                      fill="none"/>
            </g>
        </svg>;
    }

    function svgEnd(key, scale = 1.0) {
        let width = 48 * scale;
        let height = 64 * scale;

        return <svg key={key} width={width} height={height} xmlns="http://www.w3.org/2000/svg">
            <g>
                { /* vertical line */}
                <line y2="0" x2={width / 2} y1={height / 2} x1={width / 2} strokeWidth="1.5" stroke="#000" fill="none"/>
                { /* horizontal line */}
                <line y2={height / 2} x2={width} y1={height / 2} x1={width / 2} strokeWidth="1.5" stroke="#000"
                      fill="none"/>
            </g>
        </svg>;
    }

    function svgLeft(key, scale = 1.0) {
        let width = 48 * scale;
        let height = 64 * scale;
        return <svg key={key} width={width} height={height} xmlns="http://www.w3.org/2000/svg">
            <g>
                { /* vertical line */}
                <line y2="0" x2={width / 2} y1={height} x1={width / 2} strokeWidth="1.5" stroke="#000" fill="none"/>
            </g>
        </svg>;
    }

    function createFileIcon(mimeType, size = "2x") {
        let icon = "";
        if (mimeType) {
            mimeType = mimeType.toLowerCase().trim();
            let types = ["image", "text", "audio", "video"];
            let languages = ["php", "java", "python", "cpp"];
            let archives = ["zip", "tar", "archive"];
            let [mainType, subType] = mimeType.split("/");
            if (mainType === "text" && languages.find(a => subType.includes(a))) {
                icon = "code";
            } else if (mainType === "application" && archives.find(a => subType.includes(a))) {
                icon = "archive";
            } else if (mainType === "application" && subType === "pdf") {
                icon = "pdf";
            } else if (mainType === "application" && (subType.indexOf("powerpoint") > -1 || subType.indexOf("presentation") > -1)) {
                icon = "powerpoint";
            } else if (mainType === "application" && (subType.indexOf("word") > -1 || subType.indexOf("opendocument") > -1)) {
                icon = "word";
            } else if (mainType === "application" && (subType.indexOf("excel") > -1 || subType.indexOf("sheet") > -1)) {
                icon = "excel";
            } else if (mainType === "application" && subType.indexOf("directory") > -1) {
                icon = "folder";
            } else if (types.indexOf(mainType) > -1) {
                if (mainType === "text") {
                    icon = "alt";
                } else {
                    icon = mainType;
                }
            }
        }

        if (icon !== "folder") {
            icon = "file" + (icon ? ("-" + icon) : icon);
        }

        return <Icon icon={icon} type={"far"} className={"p-1 align-middle file-icon fa-" + size}/>
    }

    function formatSize(size) {
        const suffixes = ["B", "KiB", "MiB", "GiB", "TiB"];
        let i = 0;
        for (; i < suffixes.length && size >= 1024; i++) {
            size /= 1024.0;
        }

        if (i === 0 || Math.round(size) === size) {
            return size + " " + suffixes[i];
        } else {
            return size.toFixed(1) + " " + suffixes[i];
        }
    }

    useEffect(() => {
        let newFiles = filesToUpload.slice();
        for (let fileIndex = 0; fileIndex < newFiles.length; fileIndex++) {
            if (typeof newFiles[fileIndex].progress === 'undefined') {
                onUpload(fileIndex);
                break;
            }
        }
    }, [filesToUpload]);

    function onAddUploadFiles(acceptedFiles, rejectedFiles) {

        if (rejectedFiles && rejectedFiles.length > 0) {
            const filenames = rejectedFiles.map(f => f.file.name).join(", ");
            pushAlert({msg: "The following files could not be uploaded due to given restrictions: " + filenames }, "Cannot upload file");
        }

        if (acceptedFiles && acceptedFiles.length > 0) {
            let files = filesToUpload.slice();
            files.push(...acceptedFiles);
            setFilesToUpload(files);
        }
    }

    function getSelectedIds(items = null, recursive = true) {
        let ids = [];
        items = items || files;
        for (const fileItem of Object.values(items)) {
            if (fileItem.selected) {
                ids.push(fileItem.uid);
            }
            if (recursive && fileItem.isDirectory) {
                ids.push(...getSelectedIds(fileItem.items));
            }
        }

        return ids;
    }

    // TODO: add more mime type names or use an directory here?
    function getTypeName(type) {
        if (type.toLowerCase() === "directory") {
            return "Directory";
        }

        switch (type.toLowerCase()) {
            case "image/jpeg":
                return "JPEG-Image";
            case "image/png":
                return "PNG-Image";
            case "application/pdf":
                return "PDF-Document";
            case "text/plain":
                return "Text-Document"
            case "application/x-dosexec":
                return "Windows Executable";
            case "application/vnd.oasis.opendocument.text":
                return "OpenOffice-Document";
            default:
                return type;
        }
    }

    let selectedIds = getSelectedIds();
    let selectedCount = selectedIds.length;
    let uploadZone = <></>;
    let writePermissions = canUpload();
    let uploadedFiles = [];
    let alertElements = [];

    function createFileList(elements, indentation = 0) {
        let rows = [];
        let rowIndex = 0;

        const scale = 0.45;
        const iconSize = "lg";

        const values = Object.values(elements);
        for (const fileElement of values) {
            let name = fileElement.name;
            let uid = fileElement.uid;
            let type = (fileElement.isDirectory ? "Directory" : fileElement.mimeType);
            let size = (fileElement.isDirectory ? "" : formatSize(fileElement.size));
            let mimeType = (fileElement.isDirectory ? "application/x-directory" : fileElement.mimeType);
            let token = (tokenObj && tokenObj.valid ? "&token=" + tokenObj.value : "");
            let svg = [];
            if (indentation > 0) {
                for (let i = 0; i < indentation - 1; i++) {
                    svg.push(svgLeft(rowIndex + "-" + i, scale));
                }

                if (rowIndex === values.length - 1) {
                    svg.push(svgEnd(rowIndex + "-end", scale));
                } else {
                    svg.push(svgMiddle(rowIndex + "-middle", scale));
                }
            }

            rows.push(
                <tr key={"file-" + uid} data-id={uid} className={"file-row"}>
                    <td>
                        {svg}
                        {createFileIcon(mimeType, iconSize)}
                    </td>
                    <td>
                        {fileElement.isDirectory ? name :
                            <a href={"/api/file/download?id=" + uid + token} download={true}>{name}</a>
                        }
                    </td>
                    <td>{getTypeName(type)}</td>
                    <td>{size}</td>
                    <td>
                        <input type={"checkbox"} checked={!!fileElement.selected}
                               onChange={(e) => onSelectFile(e, uid)}
                        />
                        { writePermissions ?
                            <Icon icon={"pencil-alt"} title={"Rename"} className={"ml-2 clickable text-secondary"}
                                style={{marginTop: "-17px"}} onClick={() => onPopupOpen("rename", uid)} /> :
                            <></> }
                    </td>
                </tr>
            );

            if (fileElement.isDirectory) {
                rows.push(...createFileList(fileElement.items, indentation + 1));
            }
            rowIndex++;
        }
        return rows;
    }

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

    function getAllowedExtensions() {
        let extensions = restrictions.extensions || "";
        return extensions.split(",")
            .map(ext => ext.trim())
            .map(ext => !ext.startsWith(".") && ext.length > 0 ? "." + ext : ext)
            .join(",");
    }

    function getRestrictions() {
        return {
            accept: getAllowedExtensions(),
            maxFiles: restrictions.maxFiles,
            maxSize: restrictions.maxSize
        };
    }

    function onCancelUpload(e, i) {
        e.stopPropagation();
        e.preventDefault();
        const cancelToken = filesToUpload[i].cancelToken;
        if (cancelToken && filesToUpload[i].progress < 1) {
            cancelToken.cancel("Upload cancelled");
        }

        let files = filesToUpload.slice();
        files.splice(i, 1);
        setFilesToUpload(files);
    }

    let rows = createFileList(files);
    if (writePermissions) {

        for (let i = 0; i < filesToUpload.length; i++) {
            const file = filesToUpload[i];
            const progress = Math.round((file.progress ?? 0) * 100);
            const done = progress >= 100;
            uploadedFiles.push(
                <span className={"uploaded-file"} key={i}>
                        {createFileIcon(file.type, "3x")}
                    <span>{file.name}</span>
                    {!done ?
                        <div className={"progress border border-primary position-relative"}>
                            <div className={"progress-bar progress-bar-striped progress-bar-animated"} role={"progressbar"}
                                 aria-valuenow={progress} aria-valuemin={"0"} aria-valuemax={"100"}
                                 style={{width: progress + "%"}} />
                           <span className="justify-content-center d-flex position-absolute w-100" style={{top: "7px"}}>
                               { progress + "%" }
                           </span>
                        </div> : <></>
                    }
                    <Icon icon={done ? (file.success ? "check" : "times") : "spinner"}
                          className={"status-icon " + (done ? (file.success ? "text-success" : "text-danger") : "text-secondary")} />
                    <Icon icon={"times"} className={"text-danger cancel-button fa-2x"}
                          title={"Cancel Upload"} onClick={(e) => onCancelUpload(e, i)}/>
                </span>
            );
        }

        uploadZone = <>
            <div className={"p-3"}>
                <label><b>Upload Directory:</b></label>
                <select value={popup.directory} className={"form-control"}
                        onChange={(e) => onPopupChange(e, "directory")}>
                    {options}
                </select>
            </div>
            <Dropzone onDrop={onAddUploadFiles} {...getRestrictions()} >
                {({getRootProps, getInputProps}) => (
                    <section className={"file-upload-container"}>
                        <div {...getRootProps()}>
                            <input {...getInputProps()} />
                            <p>Drag 'n' drop some files here, or click to select files</p>
                            {uploadedFiles.length === 0 ?
                                <Icon className={"mx-auto fa-3x text-black-50"} icon={"upload"}/> :
                                <div>{uploadedFiles}</div>
                            }
                        </div>
                    </section>
                )}
            </Dropzone>
        </>;
    }

    let singleButton = {
        gridColumnStart: 1,
        gridColumnEnd: 3,
        width: "40%",
        margin: "0 auto"
    };

    function createPopup() {
        let title = "";
        let inputs = [];

        if (popup.type === "createDirectory" || popup.type === "moveFiles") {
            inputs.push(
                <div className={"form-group"} key={"select-directory"}>
                    <label>Destination Directory:</label>
                    <select value={popup.directory} className={"form-control"}
                            onChange={(e) => onPopupChange(e, "directory")}>
                        {options}
                    </select>
                </div>
            );
        }

        if (popup.type === "createDirectory" || popup.type === "rename") {
            inputs.push(
                <div className={"form-group"} key={"input-name"}>
                    <label>{ popup.type === "createDirectory" ? "Create Directory" : "New Name" }</label>
                    <input type={"text"} className={"form-control"} value={popup.name} maxLength={32}
                           placeholder={"Enter name…"}
                           onChange={(e) => onPopupChange(e, "name")}/>
                </div>
            );
        }

        if (popup.type === "createDirectory") {
            title = "Create Directory";
        } else if (popup.type === "moveFiles") {
            title = "Move Files";
        } else if (popup.type === "rename") {
            title = "Rename File or Directory";
        }

        return <Popup title={title} visible={popup.visible} buttons={["Ok", "Cancel"]} onClose={onPopupClose}
                      onClick={onPopupButton}>
            { inputs }
        </Popup>
    }

    return <>
        <h4>
            <Icon icon={"sync"} className={"mx-3 clickable small"} onClick={fetchFiles}/>
            File Browser
        </h4>
        <table className={"table data-table file-table"}>
            <thead>
            <tr>
                <th/>
                <th>Name</th>
                <th>Type</th>
                <th>Size</th>
                <th/>
            </tr>
            </thead>
            <tbody>
            {rows.length > 0 ? rows :
                <tr>
                    <td colSpan={4} className={"text-center text-black-50"}>
                        No files uploaded yet
                    </td>
                </tr>
            }
            </tbody>
        </table>
        <div className={"file-control-buttons"}>
            <button type={"button"} className={"btn btn-success"} disabled={selectedCount === 0} style={!writePermissions ? singleButton : {}}
                    onClick={() => onDownload(selectedIds)}>
                <Icon icon={"download"} className={"mr-1"}/>
                Download Selected Files ({selectedCount})
            </button>
            {
                writePermissions ?
                    <>
                        <button type={"button"} className={"btn btn-danger"} disabled={selectedCount === 0}
                                onClick={() => deleteFiles(selectedIds)}>
                            <Icon icon={"trash"} className={"mr-1"}/>
                            Delete Selected Files ({selectedCount})
                        </button>
                        {api.loggedIn ?
                            <>
                                <button type={"button"} className={"btn btn-info"}
                                    onClick={(e) => onPopupOpen("createDirectory")}>
                                <Icon icon={"plus"} className={"mr-1"}/>
                                Create Directory
                                </button>
                                <button type={"button"} className={"btn btn-primary"} disabled={selectedCount === 0}
                                        onClick={(e) => onPopupOpen("moveFiles")}>
                                    <Icon icon={"plus"} className={"mr-1"}/>
                                    Move Selected Files ({selectedCount})
                                </button>
                            </>:
                            <></>
                        }
                    </>
                    : <></>
            }
        </div>
        {uploadZone}
        <div className={"file-browser-restrictions px-4 mb-4"}>
            <b>Restrictions:</b>
            <span>Max. Files: {restrictions.maxFiles}</span>
            <span>Max. Filesize: {formatSize(restrictions.maxSize)}</span>
            <span>{restrictions.extensions ? "Allowed extensions: " + restrictions.extensions : "All extensions allowed"}</span>
        </div>
        <div>
            {alertElements}
        </div>
        { createPopup() }
    </>;

    function onPopupOpen(type, target = null) {
        setPopup({...popup, visible: true, type: type, target: target});
    }

    function onPopupClose() {
        setPopup({...popup, visible: false});
    }

    function onPopupChange(e, key) {
        setPopup({...popup, [key]: e.target.value});
    }

    function onPopupButton(btn) {

        if (btn === "Ok") {
            let parentId = popup.directory === 0 ? null : popup.directory;
            if (popup.type === "createDirectory") {
                api.createDirectory(popup.name, parentId).then((res) => {
                    if (!res.success) {
                        pushAlert(res, "Error creating directory");
                    } else {
                        fetchFiles();
                    }
                });
            } else if (popup.type === "moveFiles") {
                api.moveFiles(selectedIds, parentId).then((res) => {
                    if (!res.success) {
                        pushAlert(res, "Error moving files");
                    } else {
                        fetchFiles();
                    }
                });
            } else if (popup.type === "rename") {
                api.rename(popup.target, popup.name, tokenObj.valid ? tokenObj.value : null).then((res) => {
                    if (!res.success) {
                        pushAlert(res, "Error renaming file or directory");
                    } else {
                        fetchFiles();
                    }
                });
            }
        }

        onPopupClose();
    }

    function removeUploadedFiles() {
        let newFiles = filesToUpload.filter(file => !file.progress || file.progress < 1.0);
        if (newFiles.length !== filesToUpload.length) {
            setFilesToUpload(newFiles);
        }
    }

    function fetchFiles() {
        let promise;

        if (tokenObj.valid) {
            promise = api.validateToken(tokenObj.value);
        } else if (api.loggedIn) {
            promise = api.listFiles()
        } else {
            return; // should never happen
        }

        promise.then((res) => {
            if (res) {
                onFetchFiles(res.files);
                removeUploadedFiles();
            } else {
                pushAlert(res);
            }
        });
    }

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

    function deleteFiles(selectedIds) {
        if (selectedIds && selectedIds.length > 0) {
            let token = (api.loggedIn ? null : tokenObj.value);
            api.delete(selectedIds, token).then((res) => {
                if (res.success) {
                    fetchFiles();
                } else {
                    pushAlert(res);
                }
            });
        }
    }

    function onUploadProgress(event, fileIndex) {
        if (fileIndex < filesToUpload.length) {
            let files = filesToUpload.slice();
            files[fileIndex].progress = event.loaded >= event.total ? 1 : event.loaded / event.total;
            setFilesToUpload(files);
        }
    }

    function onUpload(fileIndex) {
        let token = (api.loggedIn ? null : tokenObj.value);
        let parentId = ((!api.loggedIn || popup.directory === 0) ? null : popup.directory);
        const file = filesToUpload[fileIndex];
        const cancelToken = axios.CancelToken.source();

        let newFiles = filesToUpload.slice();
        newFiles[fileIndex].cancelToken = cancelToken;
        newFiles[fileIndex].progress = 0;
        setFilesToUpload(newFiles);

        api.upload(file, token, parentId, cancelToken, (e) => onUploadProgress(e, fileIndex)).then((res) => {

            let newFiles = filesToUpload.slice();
            newFiles[fileIndex].success = res.success;
            setFilesToUpload(newFiles);

            if (res.success) {
                fetchFiles();
            } else {
                pushAlert(res);
            }
        }).catch((reason) => {
            if (reason && reason.message !== "Upload cancelled") {
                pushAlert({ msg: reason }, "Error uploading files");
            }
        });
    }

    function onDownload(selectedIds) {
        if (selectedIds && selectedIds.length > 0) {
            let token = (api.loggedIn ? "" : "&token=" + tokenObj.value);
            let ids = selectedIds.map(id => "id[]=" + id).join("&");
            let downloadUrl = "/api/file/download?" + ids + token;
            fetch(downloadUrl)
                .then(response => {
                    let header = response.headers.get("Content-Disposition") || "";
                    let fileNameFields = header.split(";").filter(c => c.trim().toLowerCase().startsWith("filename="));
                    let fileName = null;
                    if (fileNameFields.length > 0) {
                        fileName = fileNameFields[0].trim().substr("filename=".length);
                    } else {
                        fileName = null;
                    }

                    response.blob().then(blob => {
                        let url = window.URL.createObjectURL(blob);
                        let a = document.createElement('a');
                        a.href = url;
                        if (fileName !== null) a.download = fileName;
                        a.click();
                    });
                });
        }
    }
}