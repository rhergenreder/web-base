import React from 'react';
import ReactDOM from 'react-dom';
import API from "../../adminPanel/src/api";

class FileControlPanel extends React.Component {

    constructor(props) {
        super(props);
        this.api = new API();
        this.state = {
            loaded: false
        };
    }

    render() {
        return <></>;
    }

}

ReactDOM.render(
    <FileControlPanel />,
    document.getElementById('root')
);
