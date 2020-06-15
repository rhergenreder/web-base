import React from 'react';
import ReactDOM from 'react-dom';
import './include/index.css';
import './include/adminlte.min.css';
import API from './api.js';
import Header from './header.js';
import Sidebar from './sidebar.js';
import UserOverview from './users.js';
import Overview from './overview.js'
import Icon from "./icon";
import Dialog from "./dialog";
import { BrowserRouter as Router, Route, Switch } from 'react-router-dom'

class AdminDashboard extends React.Component {

  constructor(props) {
    super(props);
    this.api = new API();
    this.state = {
      loaded: false,
      dialog: { onClose: () => this.hideDialog() },
      notifications: { }
    };
  }

  onUpdate() {
    if (this.state.loaded) {
      this.fetchNotifications();
    }
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
        this.setState({...this.state, notifications: res.notifications});
      }
    });
  }

  componentDidMount() {
    this.api.fetchUser().then(Success => {
      if (!Success) {
        document.location = "/admin";
      } else {
        this.fetchNotifications();
        setInterval(this.onUpdate.bind(this), 60000);
        this.setState({...this.state, loaded: true});
      }
    });
  }

  render() {

    if (!this.state.loaded) {
      return <b>Loading… <Icon icon={"spinner"} /></b>
    }

    const controlObj = {
      notifications: this.state.notifications,
      showDialog: this.showDialog.bind(this),
      api: this.api
    };

    const createView = (view) => {
      controlObj.currentView = view;
      switch (view) {
        case "users":
          return <UserOverview {...controlObj} />;
        case "dashboard":
        default:
          return <Overview {...controlObj} />;
      }
    };

    return <Router>
        <Header {...controlObj} />
        <Sidebar {...controlObj} />
        <div className={"content-wrapper p-2"}>
          <section className={"content"}>
            <Route path={"/admin/:view"} component={(obj) => createView(obj.match.params.view)}/>
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
