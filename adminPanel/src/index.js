import React from 'react';
import ReactDOM from 'react-dom';
import './include/adminlte.min.css';
import './include/index.css';
import API from './api.js';
import Header from './elements/header.js';
import Sidebar from './elements/sidebar.js';
import UserOverview from './views/users.js';
import Overview from './views/overview.js'
import CreateUser from "./views/adduser";
import Icon from "./elements/icon";
import Dialog from "./elements/dialog";
import {BrowserRouter as Router, Route, Switch} from 'react-router-dom'
import View404 from "./views/404";
import Logs from "./views/logs";
import PageOverview from "./views/pages";
import HelpPage from "./views/help";
import Footer from "./elements/footer";
import EditUser from "./views/edituser";
import CreateGroup from "./views/addgroup";
import Settings from "./views/settings";
import PermissionSettings from "./views/permissions";
import Visitors from "./views/visitors";
import ContactRequestOverview from "./views/contact";

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
    this.fetchNotifications();
    this.fetchContactRequests();
  }

  showDialog(message, title, options=["Close"], onOption = null) {
    const props = { show: true, message: message, title: title, options: options, onOption: onOption };
    this.setState({ ...this.state, dialog: { ...this.state.dialog, ...props } });
  }

  hideDialog() {
    this.setState({ ...this.state, dialog: { ...this.state.dialog, show: false } });
  }

  fetchNotifications() {
    this.api.getNotifications().then((res) => {
      if (!res.success) {
        this.showDialog("Error fetching notifications: " + res.msg, "Error fetching notifications");
      } else {
        this.setState({...this.state, notifications: res.notifications });
      }
    });
  }

  fetchContactRequests() {
    this.api.fetchContactRequests().then((res) => {
      if (!res.success) {
        this.showDialog("Error fetching contact requests: " + res.msg, "Error fetching contact requests");
      } else {
        this.setState({...this.state, contactRequests: res.contactRequests });
      }
    });
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
      return <b>Loading… <Icon icon={"spinner"} /></b>
    }

    this.controlObj = {
      showDialog: this.showDialog.bind(this),
      fetchNotifications: this.fetchNotifications.bind(this),
      api: this.api
    };

    return <Router>
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
  }
}

ReactDOM.render(
    <AdminDashboard />,
    document.getElementById('root')
);
