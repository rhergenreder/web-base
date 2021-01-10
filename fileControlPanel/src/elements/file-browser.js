import * as React from "react";

export class FileBrowser extends React.Component {

    constructor(props) {
        super(props);

        this.state = {
            files: props.files,
        }
    }

    formatSize(size) {
        const suffixes = ["B","KiB","MiB","GiB","TiB"];
        let i = 0;
        for (; i < suffixes.length && size >= 1024; i++) {
            size /= 1024.0;
        }

        return size.toFixed(1) + " " + suffixes[i];
    }

    render() {
        
        let rows = [];
        for (const [uid, fileElement] of Object.entries(this.state.files)) {
            let name = fileElement.name;
            let type = (fileElement.directory ? "Directory" : fileElement.mimeType);
            let size = (fileElement.directory ? "" : fileElement.size)
            // let iconUrl = (fileElement.directory ? "/img/icon/")
            let iconUrl = "";

            rows.push(
                <tr key={"file-" + uid}>
                    <td><img src={iconUrl} alt={"[Icon]"} /></td>
                    <td>{name}</td>
                    <td>{type}</td>
                    <td>{this.formatSize(size)}</td>
                </tr>
            );
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
                    </tr>
                </thead>
                <tbody>
                    { rows }
                </tbody>
            </table>
        </>;
    }
}