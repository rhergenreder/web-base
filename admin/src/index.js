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

class AdminDashboard extends React.Component {

  constructor(props) {
    super(props);
    this.api = new API();
    this.state = {
      currentView: "dashboard",
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

  onChangeView(view) {
    this.setState({ ...this.state, currentView: view || "dashboard" });
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

  render() {

    if (!this.state.loaded) {
      this.api.fetchUser().then(Success => {
        if (!Success) {
          document.location = "/admin";
        } else {
          this.fetchNotifications();
          setInterval(this.onUpdate.bind(this), 60000);
          this.setState({...this.state, loaded: true});
        }
      });
      return <b>Loading… <Icon icon={"spinner"} /></b>
    }

    const controlObj = {
      notifications: this.state.notifications,
      currentView: this.state.currentView,
      onChangeView: this.onChangeView.bind(this),
      showDialog: this.showDialog.bind(this),
      api: this.api
    };

    return <>
        <Header {...controlObj} />
        <Sidebar {...controlObj} />
        <div className={"content-wrapper p-2"}>
          <section className={"content"}>
            {this.createContent()}
            <Dialog {...this.state.dialog}/>
          </section>
        </div>
      </>
  }

  createContent() {
    if (this.state.currentView === "users") {
      return <UserOverview />
    } else {
      return <Overview />
    }
  }
}

ReactDOM.render(
  <AdminDashboard />,
  document.getElementById('root')
);
