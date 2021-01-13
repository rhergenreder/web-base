import * as React from "react";
import "./file-browser.css";
import Dropzone from "react-dropzone";
import Icon from "./icon";

export class FileBrowser extends React.Component {

    constructor(props) {
        super(props);

        this.state = {
            api: props.api,
            files: props.files,
            token: props.token,
            filesToUpload: [],
            alerts: []
        }
    }

    svgMiddle(indentation, scale=1.0) {
        let width = 48 * scale;
        let height = 64 * scale;
        let style = (indentation > 1 ? { marginLeft: ((indentation-1)*width) + "px" } : {});

        return <svg width={width} height={height} xmlns="http://www.w3.org/2000/svg" style={style}>
            <g>
                <line strokeLinecap="undefined" strokeLinejoin="undefined" y2="0" x2={width/2}
                      y1={height} x1={width/2} strokeWidth="1.5" stroke="#000" fill="none"/>
                <line strokeLinecap="undefined" strokeLinejoin="undefined" y2={height/2} x2={width}
                      y1={height/2} x1={width/2} fillOpacity="null" strokeOpacity="null" strokeWidth="1.5"
                      stroke="#000" fill="none"/>
            </g>
        </svg>;
    }

    svgEnd(indentation, scale=1.0) {
        let width = 48 * scale;
        let height = 64 * scale;
        let style = (indentation > 1 ? { marginLeft: ((indentation-1)*width) + "px" } : {});

        return <svg width={width} height={height} xmlns="http://www.w3.org/2000/svg" style={style}>
            <g>
                { /* vertical line */}
                <line strokeLinecap="undefined" strokeLinejoin="undefined" y2="0" x2={width/2}
                      y1={height/2} x1={width/2} strokeWidth="1.5" stroke="#000" fill="none"/>
                { /* horizontal line */}
                <line strokeLinecap="undefined" strokeLinejoin="undefined" y2={height/2} x2={width}
                      y1={height/2} x1={width/2} fillOpacity="null" strokeOpacity="null" strokeWidth="1.5"
                      stroke="#000" fill="none"/>
            </g>
        </svg>;
    }

    createFileIcon(mimeType, size=2) {
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

        return <Icon icon={icon} type={"far"} className={"p-1 align-middle fa-" + size + "x"} />
    }

    formatSize(size) {
        const suffixes = ["B","KiB","MiB","GiB","TiB"];
        let i = 0;
        for (; i < suffixes.length && size >= 1024; i++) {
            size /= 1024.0;
        }

        return size.toFixed(1) + " " + suffixes[i];
    }

    canUpload() {
        return this.state.api.loggedIn || (this.state.token.valid && this.state.token.type === "upload");
    }

    onAddUploadFiles(acceptedFiles) {
        let files = this.state.filesToUpload.slice();
        files.push(...acceptedFiles);
        this.setState({ ...this.state, filesToUpload: files });
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

    createFileList(elements, indentation=0) {
        let rows = [];
        let i = 0;
        const values = Object.values(elements);
        for (const fileElement of values) {
            let name = fileElement.name;
            let uid  = fileElement.uid;
            let type = (fileElement.isDirectory ? "Directory" : fileElement.mimeType);
            let size = (fileElement.isDirectory ? "" : this.formatSize(fileElement.size));
            let mimeType = (fileElement.isDirectory ? "application/x-directory" : fileElement.mimeType);
            let token = (this.state.token && this.state.token.valid ? "&token=" + this.state.token.value : "");
            let svg = <></>;
            if (indentation > 0) {
                if (i === values.length - 1) {
                    svg = this.svgEnd(indentation, 0.75);
                } else {
                    svg = this.svgMiddle(indentation, 0.75);
                }
            }

            rows.push(
                <tr key={"file-" + uid} data-id={uid} className={"file-row"}>
                    <td>
                        { svg }
                        { this.createFileIcon(mimeType) }
                    </td>
                    <td>
                        {fileElement.isDirectory ? name :
                            <a href={"/api/file/download?id=" + uid + token} download={true}>{name}</a>
                        }
                    </td>
                    <td>{type}</td>
                    <td>{size}</td>
                    <td>
                        <input type={"checkbox"} checked={!!fileElement.selected}
                               onChange={(e) => this.onSelectFile(e, uid)}
                        />
                    </td>
                </tr>
            );

            if (fileElement.isDirectory) {
                rows.push(...this.createFileList(fileElement.items, indentation + 1));
            }
            i++;
        }
        return rows;
    }

    render() {
        
        let rows = this.createFileList(this.state.files);
        let selectedIds = this.getSelectedIds();
        let selectedCount = selectedIds.length;
        let uploadZone = <></>;
        let writePermissions = this.canUpload();
        let uploadedFiles = [];
        let alerts = [];

        let i = 0;
        for (const alert of this.state.alerts) {
            alerts.push(
                <div key={"alert-" + i++} className={"alert alert-" + alert.type}>
                    { alert.text }
                </div>
            );
        }

        if (writePermissions) {

            for(let i = 0; i < this.state.filesToUpload.length; i++) {
                const file = this.state.filesToUpload[i];
                uploadedFiles.push(
                    <span className={"uploaded-file"} key={i}>
                        { this.createFileIcon(file.type, 3) }
                        <span>{file.name}</span>
                        <Icon icon={"times"} onClick={(e) => this.onRemoveUploadedFile(e, i)}/>
                    </span>
                );
            }

            uploadZone = <><Dropzone onDrop={this.onAddUploadFiles.bind(this)}>
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
            <h4>File Browser</h4>
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
                    { rows }
                </tbody>
            </table>
            <div className={"file-control-buttons"}>
                <button type={"button"} className={"btn btn-success"} disabled={selectedCount === 0}
                        onClick={() => this.onDownload(selectedIds)}>
                    <Icon icon={"download"} className={"mr-1"}/>
                    Download Selected Files ({selectedCount})
                </button>
                { this.state.api.loggedIn ?
                    <button type={"button"} className={"btn btn-info"}>
                        <Icon icon={"plus"} className={"mr-1"}/>
                        Create Directory
                    </button> :
                    <></>
                }
                {
                    writePermissions ?
                        <>
                            <button type={"button"} className={"btn btn-primary"} disabled={uploadedFiles.length === 0}
                                    onClick={this.onUpload.bind(this)}>
                                <Icon icon={"upload"} className={"mr-1"}/>
                                Upload
                            </button>
                            <button type={"button"} className={"btn btn-danger"} disabled={selectedCount === 0}
                                    onClick={() => this.deleteFiles(selectedIds)}>
                                <Icon icon={"trash"} className={"mr-1"}/>
                                Delete Selected Files ({selectedCount})
                            </button>
                        </>
                    : <></>
                }
            </div>
            { uploadZone }
            <div>
                { alerts }
            </div>
        </>;
    }

    fetchFiles() {
        if (this.state.token.valid) {
            this.state.api.validateToken(this.state.token.value).then((res) => {
                if (res) {
                    this.setState({ ...this.state, files: res.files });
                } else {
                    this.pushAlert(res);
                }
            });
        } else if (this.state.api.loggedIn) {
            this.state.api.listFiles().then((res) => {
                if (res) {
                    this.setState({ ...this.state, files: res.files });
                } else {
                    this.pushAlert(res);
                }
            });
        }
    }

    onRemoveUploadedFile(e, i) {
        e.stopPropagation();
        let files = this.state.filesToUpload.slice();
        files.splice(i, 1);
        this.setState({ ...this.state, filesToUpload: files });
    }

    pushAlert(res) {
        let newAlerts = this.state.alerts.slice();
        newAlerts.push({ type: "danger", text: res.msg });
        this.setState({ ...this.state, alerts: newAlerts });
    }

    deleteFiles(selectedIds) {
        if (selectedIds && selectedIds.length > 0) {
            let token = (this.state.api.loggedIn ? null : this.state.token.value);
            this.state.api.delete(selectedIds, token).then((res) => {
               if (res.success) {
                    this.fetchFiles();
               } else {
                   this.pushAlert(res);
               }
            });
        }
    }

    onUpload() {
        let token = (this.state.api.loggedIn ? null : this.state.token.value);
        this.state.api.upload(this.state.filesToUpload, token).then((res) => {
            if (res.success) {
                this.setState({ ...this.state, filesToUpload: [] })
                this.fetchFiles();
            } else {
                this.pushAlert(res);
            }
        });
    }

    onDownload(selectedIds) {
        if (selectedIds && selectedIds.length > 0) {
            let token = (this.state.api.loggedIn ? "" : "&token=" + this.state.token.value);
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