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
        }
    }

    svgMiddle(indentation, size=64) {
        let style = (indentation > 1 ? { marginLeft: ((indentation-1)*size) + "px" } : {});
        return <svg width={size} height={size} xmlns="http://www.w3.org/2000/svg" style={style}>
            <g>
                <line strokeLinecap="undefined" strokeLinejoin="undefined" y2="0" x2={size/2}
                      y1={size} x1={size/2} strokeWidth="1.5" stroke="#000" fill="none"/>
                <line strokeLinecap="undefined" strokeLinejoin="undefined" y2={size/2} x2={size}
                      y1={size/2} x1={size/2} fillOpacity="null" strokeOpacity="null" strokeWidth="1.5"
                      stroke="#000" fill="none"/>
            </g>
        </svg>;
    }

    svgEnd(indentation, size=64) {
        let style = (indentation > 1 ? { marginLeft: ((indentation-1)*size) + "px" } : {});
        return <svg width={size} height={size} xmlns="http://www.w3.org/2000/svg" style={style}>
            <g>
                { /* vertical line */}
                <line strokeLinecap="undefined" strokeLinejoin="undefined" y2="0" x2={size/2}
                      y1={size/2} x1={size/2} strokeWidth="1.5" stroke="#000" fill="none"/>
                { /* horizontal line */}
                <line strokeLinecap="undefined" strokeLinejoin="undefined" y2={size/2} x2={size}
                      y1={size/2} x1={size/2} fillOpacity="null" strokeOpacity="null" strokeWidth="1.5"
                      stroke="#000" fill="none"/>
            </g>
        </svg>;
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
            // let iconUrl = (fileElement.directory ? "/img/icon/")
            let iconUrl = "";
            let token = (this.state.token && this.state.token.valid ? "&token=" + this.token.state.value : "");
            let svg = <></>;
            if (indentation > 0) {
                if (i === values.length - 1) {
                    svg = this.svgEnd(indentation, 48);
                } else {
                    svg = this.svgMiddle(indentation, 48);
                }
            }

            rows.push(
                <tr key={"file-" + uid} data-id={uid} className={"file-row"}>
                    <td>
                        { svg }
                        <img src={iconUrl} alt={"[Icon]"} />
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

        if (writePermissions) {

            for(let i = 0; i < this.state.filesToUpload.length; i++) {
                const file = this.state.filesToUpload[i];
                uploadedFiles.push(
                    <span className={"uploaded-file"} key={i}>
                        <img />
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
                            { uploadedFiles.length === 0 ? <Icon className={"mx-auto fa-3x text-black-50"} icon={"upload"}/> : <div>{uploadedFiles}</div> }
                        </div>
                    </section>
                 )}
             </Dropzone>
           </>;
        }

        return <>
            <h4>File Browser</h4>
            <table className={"table"}>
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
                <button type={"button"} className={"btn btn-success"} disabled={selectedCount === 0}>
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
                            <button type={"button"} className={"btn btn-primary"} disabled={uploadedFiles.length === 0}>
                                <Icon icon={"upload"} className={"mr-1"}/>
                                Upload
                            </button>
                            <button type={"button"} className={"btn btn-danger"} disabled={selectedCount === 0} onClick={(e) => this.deleteFiles(selectedIds)}>
                                <Icon icon={"trash"} className={"mr-1"}/>
                                Delete Selected Files ({selectedCount})
                            </button>
                        </>
                    : <></>
                }
            </div>

            { uploadZone }
        </>;
    }

    onRemoveUploadedFile(e, i) {
        e.stopPropagation();
        let files = this.state.filesToUpload.slice();
        files.splice(i, 1);
        this.setState({ ...this.state, filesToUpload: files });
    }

    deleteFiles(selectedIds) {
        // TODO: delete files
        this.state.api.delete(selectedIds).then((res) => {
           if (res.success) {
           }
        });
    }
}