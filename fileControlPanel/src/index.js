import React from 'react';
import ReactDOM from 'react-dom';
import API from "./api";



class FileControlPanel extends React.Component {

    constructor(props) {
        super(props);
        this.api = new API();
        this.state = {
            loaded: false
        };
    }

    render() {

        if (!this.state.loaded) {
            this.api.fetchUser().then(() => {
                this.setState({ ...this.state, loaded: true });
            });
        } else if (this.state.user.loggedIn) {

        } else {

        }

        return <></>;
    }

}

ReactDOM.render(
    <FileControlPanel />,
    document.getElementById('root')
);
