import React from 'react';
import ReactDOM from 'react-dom';
import './include/index.css';
import './include/adminlte.min.css';
// import './include/adminlte.min.js';
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
      dialog: { hidden: true }
    };
  }

  onChangeView(view) {
    console.log("changing view to: " + view);
    this.setState({ ...this.state, currentView: view || "dashboard" });
  }

  showDialog(props) {
    props = props || { hidden: true };
    if (!props.hasOwnProperty("hidden")) props.hidden = false;
    this.setState({ ...this.state, dialog: props });
  }

  render() {

    if (!this.state.loaded) {
      this.api.fetchUser().then(Success => {
        if (!Success) {
          document.location = "/admin";
        } else {
          this.setState({...this.state, loaded: true});
        }
      });
      return <b>Loading… <Icon icon={"spinner"} /></b>
    }

    console.log("Rendering mainview with:", this.state.dialog);
    const dialog = <Dialog {...this.state.dialog}/>
    const content = this.createContent();

    return <div className={"wrapper"}>
      <Header />
      <Sidebar currentView={this.state.currentView} onChangeView={this.onChangeView.bind(this)} showDialog={this.showDialog.bind(this)} api={this.api} />
      <div className={"content-wrapper p-2"}>
        <section className={"content"}>
          {content}
          {dialog}
        </section>
      </div>
    </div>
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
