import * as React from "react";
import "./file-browser.css";
import Dropzone from "react-dropzone";
import Icon from "./icon";
import Alert from "./alert";
import {Popup} from "./popup";
import {useState} from "react";

export function FileBrowser(props) {

    let files = props.files || { };
    let api = props.api;
    let tokenObj = props.token || { valid: false };
    let onSelectFile = props.onSelectFile || function() { };
    let onFetchFiles = props.onFetchFiles || function() { };
    let directories  = props.directories  || {};
    let restrictions = props.restrictions || { maxFiles: 0, maxSize: 0, extensions: "" };

    let [popup, setPopup] = useState({ visible: false, directoryName: "", directory: 0, type: "upload" });
    let [alerts, setAlerts] = useState( []);
    let [filesToUpload, setFilesToUpload] = useState([]);

    function svgMiddle(scale=1.0) {
        let width = 48 * scale;
        let height = 64 * scale;

        return <svg width={width} height={height} xmlns="http://www.w3.org/2000/svg">
            <g>
                <line y2="0" x2={width/2} y1={height} x1={width/2} strokeWidth="1.5" stroke="#000" fill="none"/>
                <line y2={height/2} x2={width} y1={height/2} x1={width/2} strokeWidth="1.5" stroke="#000" fill="none"/>
            </g>
        </svg>;
    }

    function svgEnd(scale=1.0) {
        let width = 48 * scale;
        let height = 64 * scale;

        return <svg width={width} height={height} xmlns="http://www.w3.org/2000/svg">
            <g>
                { /* vertical line */}
                <line y2="0" x2={width/2} y1={height/2} x1={width/2} strokeWidth="1.5" stroke="#000" fill="none"/>
                { /* horizontal line */}
                <line y2={height/2} x2={width} y1={height/2} x1={width/2} strokeWidth="1.5" stroke="#000" fill="none"/>
            </g>
        </svg>;
    }

    function svgLeft(scale=1.0) {
        let width = 48 * scale;
        let height = 64 * scale;
        return <svg width={width} height={height} xmlns="http://www.w3.org/2000/svg" style={{}}>
            <g>
                { /* vertical line */}
                <line y2="0" x2={width/2} y1={height} x1={width/2} strokeWidth="1.5" stroke="#000" fill="none"/>
            </g>
        </svg>;
    }

    function createFileIcon(mimeType, size="2x") {
        let icon = "";
        if (mimeType !== null) {
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

        return <Icon icon={icon} type={"far"} className={"p-1 align-middle fa-" + size} />
    }

    function formatSize(size) {
        const suffixes = ["B","KiB","MiB","GiB","TiB"];
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

    function canUpload() {
        return api.loggedIn || (tokenObj.valid && tokenObj.type === "upload");
    }

    function onAddUploadFiles(acceptedFiles) {
        let files = filesToUpload.slice();
        files.push(...acceptedFiles);
        setFilesToUpload(files);
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

    function createFileList(elements, indentation=0) {
        let rows = [];
        let i = 0;

        const scale = 0.45;
        const iconSize = "lg";

        const values = Object.values(elements);
        for (const fileElement of values) {
            let name = fileElement.name;
            let uid  = fileElement.uid;
            let type = (fileElement.isDirectory ? "Directory" : fileElement.mimeType);
            let size = (fileElement.isDirectory ? "" : formatSize(fileElement.size));
            let mimeType = (fileElement.isDirectory ? "application/x-directory" : fileElement.mimeType);
            let token = (tokenObj && tokenObj.valid ? "&token=" + tokenObj.value : "");
            let svg = [];
            if (indentation > 0) {
                for (let i = 0; i < indentation - 1; i++) {
                    svg.push(svgLeft(scale));
                }

                if (i === values.length - 1) {
                    svg.push(svgEnd(scale));
                } else {
                    svg.push(svgMiddle(scale));
                }
            }

            rows.push(
                <tr key={"file-" + uid} data-id={uid} className={"file-row"}>
                    <td>
                        { svg }
                        { createFileIcon(mimeType, iconSize) }
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
                    </td>
                </tr>
            );

            if (fileElement.isDirectory) {
                rows.push(...createFileList(fileElement.items, indentation + 1));
            }
            i++;
        }
        return rows;
    }

        let rows = createFileList(files);
        let selectedIds = getSelectedIds();
        let selectedCount = selectedIds.length;
        let uploadZone = <></>;
        let writePermissions = canUpload();
        let uploadedFiles = [];
        let alertElements = [];

        for (let i = 0; i < alerts.length; i++) {
            const alert = alerts[i];
            alertElements.push(
                <Alert key={"alert-" + i} {...alert} onClose={() => removeAlert(i)} />
            );
        }

        let options = [];
        for (const [uid, dir] of Object.entries(directories)) {
            options.push(
                <option key={"option-" + dir} value={uid}>{dir}</option>
            );
        }

        if (writePermissions) {

            for(let i = 0; i < filesToUpload.length; i++) {
                const file = filesToUpload[i];
                uploadedFiles.push(
                    <span className={"uploaded-file"} key={i}>
                        { createFileIcon(file.type, "3x") }
                        <span>{file.name}</span>
                        <Icon icon={"times"} onClick={(e) => onRemoveUploadedFile(e, i)}/>
                    </span>
                );
            }

            uploadZone = <><Dropzone onDrop={onAddUploadFiles}>
                {({getRootProps, getInputProps}) => (
                    <section className={"file-upload-container"}>
                        <div {...getRootProps()}>
                            <input {...getInputProps()} />
                            <p>Drag 'n' drop some files here, or click to select files</p>
                            { uploadedFiles.length === 0 ?
                                <Icon className={"mx-auto fa-3x text-black-50"} icon={"upload"}/> :
                                <div>{uploadedFiles}</div>
                            }
                        </div>
                    </section>
                 )}
             </Dropzone>
           </>;
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
                    { rows.length > 0 ? rows :
                        <tr>
                            <td colSpan={4} className={"text-center text-black-50"}>
                                No files uploaded yet
                            </td>
                        </tr>
                    }
                </tbody>
            </table>
            <div className={"file-control-buttons"}>
                <button type={"button"} className={"btn btn-success"} disabled={selectedCount === 0}
                        onClick={() => onDownload(selectedIds)}>
                    <Icon icon={"download"} className={"mr-1"}/>
                    Download Selected Files ({selectedCount})
                </button>
                { api.loggedIn ?
                    <button type={"button"} className={"btn btn-info"} onClick={(e) => onPopupOpen("createDirectory")}>
                        <Icon icon={"plus"} className={"mr-1"}/>
                        Create Directory
                    </button> :
                    <></>
                }
                {
                    writePermissions ?
                        <>
                            <button type={"button"} className={"btn btn-primary"} disabled={uploadedFiles.length === 0}
                                    onClick={(e) => api.loggedIn ? onPopupOpen("upload") : onUpload()}>
                                <Icon icon={"upload"} className={"mr-1"}/>
                                Upload
                            </button>
                            <button type={"button"} className={"btn btn-danger"} disabled={selectedCount === 0}
                                    onClick={() => deleteFiles(selectedIds)}>
                                <Icon icon={"trash"} className={"mr-1"}/>
                                Delete Selected Files ({selectedCount})
                            </button>
                        </>
                    : <></>
                }
            </div>
            { uploadZone }
            <div className={"file-browser-restrictions px-4 mb-4"}>
                <b>Restrictions:</b>
                <span>Max. Files: { restrictions.maxFiles }</span>
                <span>Max. Filesize: { formatSize(restrictions.maxSize) }</span>
                <span>{ restrictions.extensions ? "Allowed extensions: " + restrictions.extensions : "All extensions allowed" }</span>
            </div>
            <div>
                { alertElements }
            </div>
            <Popup title={"Create Directory"} visible={popup.visible} buttons={["Ok","Cancel"]} onClose={onPopupClose} onClick={onPopupButton}>
                <div className={"form-group"}>
                    <label>Destination Directory:</label>
                    <select value={popup.directory} className={"form-control"}
                            onChange={(e) => onPopupChange(e, "directory")}>
                        { options }
                    </select>
                </div>
                { popup.type !== "upload" ?
                    <div className={"form-group"}>
                        <label>Directory Name</label>
                        <input type={"text"} className={"form-control"} value={popup.directoryName} maxLength={32} placeholder={"Enter name…"}
                               onChange={(e) => onPopupChange(e, "directoryName")}/>
                    </div> : <></>
                }
            </Popup>
        </>;

    function onPopupOpen(type) {
        setPopup({ ...popup, visible: true, type: type });
    }

    function onPopupClose() {
        setPopup({ ...popup, visible: false });
    }

    function onPopupChange(e, key) {
        setPopup({ ...popup, [key]: e.target.value });
    }

    function onPopupButton(btn) {

        if (btn === "Ok") {
            let parentId = popup.directory === 0 ? null : popup.directory;
            if (popup.type === "createDirectory") {
                api.createDirectory(popup.directoryName, parentId).then((res) => {
                    if (!res.success) {
                        pushAlert(res, "Error creating directory");
                    } else {
                        fetchFiles();
                    }
                });
            } else if (popup.type === "upload") {
                onUpload();
            }
        }

        onPopupClose();
    }

    function fetchFiles() {
        if (tokenObj.valid) {
            api.validateToken(tokenObj.value).then((res) => {
                if (res) {
                    onFetchFiles(res.files);
                } else {
                    pushAlert(res);
                }
            });
        } else if (api.loggedIn) {
            api.listFiles().then((res) => {
                if (res) {
                    onFetchFiles(res.files);
                } else {
                    pushAlert(res);
                }
            });
        }
    }

    function onRemoveUploadedFile(e, i) {
        e.stopPropagation();
        let files = filesToUpload.slice();
        files.splice(i, 1);
        setFilesToUpload(files);
    }

    function pushAlert(res, title) {
        let newAlerts = alerts.slice();
        newAlerts.push({ type: "danger", message: res.msg, title: title });
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

    function onUpload() {
        let token = (api.loggedIn ? null : tokenObj.value);
        let parentId = ((!api.loggedIn || popup.directory === 0) ? null : popup.directory);
        api.upload(filesToUpload, token, parentId).then((res) => {
            if (res.success) {
                setFilesToUpload([]);
                fetchFiles();
            } else {
                pushAlert(res);
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