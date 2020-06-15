import React from 'react';
import ReactDOM from 'react-dom';
import './include/adminlte.min.css';
import './include/index.css';
import API from './api.js';
import Header from './header.js';
import Sidebar from './sidebar.js';
import UserOverview from './users.js';
import Overview from './overview.js'
import Icon from "./icon";
import Dialog from "./dialog";
import {BrowserRouter as Router, Route} from 'react-router-dom'
import View404 from "./404";
import Switch from "react-router-dom/es/Switch";

class AdminDashboard extends React.Component {

  constructor(props) {
    super(props);
    this.api = new API();
    this.state = {
      loaded: false,
      dialog: { onClose: () => this.hideDialog() },
      notifications: { }
    };
    this.controlObj = {
      showDialog: this.showDialog.bind(this),
      api: this.api
    };
  }

  onUpdate() {
    this.fetchNotifications();
  }

  showDialog(message, title) {
    const props = { show: true, message: message, title: title };
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

  componentDidMount() {
    this.api.fetchUser().then(Success => {
      if (!Success) {
        document.location = "/admin";
      } else {
        this.fetchNotifications();
        setInterval(this.onUpdate.bind(this), 60*1000);
        this.setState({...this.state, loaded: true});
      }
    });
  }

  render() {

    if (!this.state.loaded) {
      return <b>Loading… <Icon icon={"spinner"} /></b>
    }

    return <Router>
        <Header {...this.controlObj} notifications={this.state.notifications} />
        <Sidebar {...this.controlObj} notifications={this.state.notifications} />
        <div className={"content-wrapper p-2"}>
          <section className={"content"}>
            <Switch>
              <Route path={"/admin/dashboard"}><Overview {...this.controlObj} /></Route>
              <Route path={"/admin/users"}><UserOverview {...this.controlObj} /></Route>
              <Route path={"*"}><View404 /></Route>
            </Switch>
            <Dialog {...this.state.dialog}/>
          </section>
        </div>
      </Router>
  }
}

ReactDOM.render(
    <AdminDashboard />,
    document.getElementById('root')
);
