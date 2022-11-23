import React from 'react';
import ReactDOM from 'react-dom';
import './res/adminlte.min.css';
import './res/index.css';
import API from "shared/api";
import Icon from "shared/elements/icon";

class AdminDashboard extends React.Component {

    constructor(props) {
        super(props);
        this.api = new API();
        this.state = {
            loaded: false,
            dialog: { onClose: () => this.hideDialog() },
            notifications: [ ],
            contactRequests: [ ]
        };
    }

    onUpdate() {
    }

    showDialog(message, title, options=["Close"], onOption = null) {
        const props = { show: true, message: message, title: title, options: options, onOption: onOption };
        this.setState({ ...this.state, dialog: { ...this.state.dialog, ...props } });
    }

    hideDialog() {
        this.setState({ ...this.state, dialog: { ...this.state.dialog, show: false } });
    }

    componentDidMount() {
        this.api.fetchUser().then(Success => {
            if (!Success) {
                document.location = "/admin";
            } else {
                this.fetchNotifications();
                this.fetchContactRequests();
                setInterval(this.onUpdate.bind(this), 60*1000);
                this.setState({...this.state, loaded: true});
            }
        });
    }

    render() {

        if (!this.state.loaded) {
            return <b>Loading… <Icon icon={"spinner"}/></b>
        }

        this.controlObj = {
            showDialog: this.showDialog.bind(this),
            api: this.api
        };

        return <b>test</b>
        /*return <Router>
            <Header {...this.controlObj} notifications={this.state.notifications} />
            <Sidebar {...this.controlObj} notifications={this.state.notifications} contactRequests={this.state.contactRequests}/>
            <div className={"content-wrapper p-2"}>
                <section className={"content"}>
                    <Switch>
                        <Route path={"/admin/dashboard"}><Overview {...this.controlObj} notifications={this.state.notifications} /></Route>
                        <Route exact={true} path={"/admin/users"}><UserOverview {...this.controlObj} /></Route>
                        <Route path={"/admin/user/add"}><CreateUser {...this.controlObj} /></Route>
                        <Route path={"/admin/user/edit/:userId"} render={(props) => {
                            let newProps = {...props, ...this.controlObj};
                            return <EditUser {...newProps} />
                        }}/>
                        <Route path={"/admin/user/permissions"}><PermissionSettings {...this.controlObj}/></Route>
                        <Route path={"/admin/group/add"}><CreateGroup {...this.controlObj} /></Route>
                        <Route exact={true} path={"/admin/contact/"}><ContactRequestOverview {...this.controlObj} /></Route>
                        <Route path={"/admin/visitors"}><Visitors {...this.controlObj} /></Route>
                        <Route path={"/admin/logs"}><Logs {...this.controlObj} notifications={this.state.notifications} /></Route>
                        <Route path={"/admin/settings"}><Settings {...this.controlObj} /></Route>
                        <Route path={"/admin/pages"}><PageOverview {...this.controlObj} /></Route>
                        <Route path={"/admin/help"}><HelpPage {...this.controlObj} /></Route>
                        <Route path={"*"}><View404 /></Route>
                    </Switch>
                    <Dialog {...this.state.dialog}/>
                </section>
            </div>
            <Footer />
        </Router>
    }*/
    }
}

ReactDOM.render(
    <AdminDashboard />,
    document.getElementById('admin-panel')
);
