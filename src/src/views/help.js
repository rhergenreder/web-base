import React, {useState} from "react";
import {Link} from "react-router-dom";
import Icon from "../elements/icon";
import {Collapse} from "react-collapse";

export default function HelpPage(props) {

    const [firstStepsCollapsed, collapseFirstSteps] = useState(false);
    const [faqCollapsed, collapseFaq] = useState(false);
    const [aboutCollapsed, collapseAbout] = useState(false);

    return (
        <>
            <section className={"content-header"}>
                <div className={"container-fluid"}>
                    <div className={"row mb-2"}>
                        <div className={"col-sm-6"}>
                            <h1>WebBase Help & Information</h1>
                        </div>
                        <div className={"col-sm-6"}>
                            <ol className={"breadcrumb float-sm-right"}>
                                <li className={"breadcrumb-item"}><Link to={"/admin/dashboard"}>Home</Link></li>
                                <li className={"breadcrumb-item active"}>Help</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </section>
            <section className="content">
                <div className={"container-fluid"}>
                    <div className={"row"}>
                        <div className="col-12 col-md-8 col-lg-4">
                            <p>
                                WebBase is a php framework to simplify user management, pages and routing.
                                It can easily be modified and extended by writing document classes or
                                access the database with the available abstracted scheme. It also includes
                                a REST API with access control, parameter type checking and more.
                            </p>
                        </div>
                    </div>
                    <div className={"row"}>
                        <div className={"col-12 col-lg-6"}>
                            <div className={"card"}>
                                <div className="card-header">
                                    <h3 className="card-title">
                                        <Icon icon={"walking"} className={"mr-1"}/>
                                        First Steps
                                    </h3>
                                    <div className={"card-tools"}>
                                        <button type={"button"} className={"btn btn-tool"} onClick={(e) => {
                                            e.preventDefault();
                                            collapseFirstSteps(!firstStepsCollapsed);
                                        }}>
                                            <Icon icon={"minus"} />
                                        </button>
                                    </div>
                                </div>
                                <Collapse isOpened={!firstStepsCollapsed}>
                                    <div className="card-body">
                                        <ol>
                                            <li>Customize <Link to={"/admin/settings"}>website settings</Link></li>
                                            <li>Manage users and groups on <Link to={"/admin/users"}>this page</Link></li>
                                            <li><Link to={"/admin/pages"}>Create routes</Link> for your website</li>
                                            <li>For dynamic pages:
                                                <ol>
                                                    <li>Create a document class in <b>/Core/Documents</b> according to the other classes</li>
                                                    <li>Create a view class in <b>/Core/Views</b> for every view you have</li>
                                                </ol>
                                            </li>
                                            <li>For static pages:
                                                <ul>
                                                    <li>Create html files in <b>/static</b></li>
                                                </ul>
                                            </li>
                                        </ol>
                                    </div>
                                </Collapse>
                            </div>
                        </div>
                        <div className={"col-12 col-lg-6"}>
                            <div className={"card"}>
                                <div className="card-header">
                                    <h3 className="card-title">
                                        <Icon icon={"question-circle"} className={"mr-1"}/>
                                        FAQ
                                    </h3>
                                    <div className={"card-tools"}>
                                        <button type={"button"} className={"btn btn-tool"} onClick={(e) => {
                                            e.preventDefault();
                                            collapseFaq(!faqCollapsed);
                                        }}>
                                            <Icon icon={"minus"} />
                                        </button>
                                    </div>
                                </div>
                                <Collapse isOpened={!faqCollapsed}>
                                    <div className="card-body">
                                        Nobody asked questions so far…
                                    </div>
                                </Collapse>
                            </div>
                        </div>
                        <div className={"col-12 col-lg-6"}>
                            <div className={"card"}>
                                <div className="card-header">
                                    <h3 className="card-title">
                                        <Icon icon={"address-card"} className={"mr-1"}/>
                                        About
                                    </h3>
                                    <div className={"card-tools"}>
                                        <button type={"button"} className={"btn btn-tool"} onClick={(e) => {
                                            e.preventDefault();
                                            collapseAbout(!aboutCollapsed);
                                        }}>
                                            <Icon icon={"minus"} />
                                        </button>
                                    </div>
                                </div>
                                <Collapse isOpened={!aboutCollapsed}>
                                    <div className="card-body">
                                        <b>Project Lead & Main Developer</b>
                                        <ul className={"list-unstyled"}>
                                            <li><small><Icon icon={"address-card"} className={"mr-1"}/>Roman Hergenreder</small></li>
                                            <li><small><Icon icon={"globe"} className={"mr-1"}/><a href={"https://romanh.de/"} target={"_blank"}>https://romanh.de/</a></small></li>
                                            <li><small><Icon icon={"envelope"} className={"mr-1"}/><a href={"mailto:webmaster@romanh.de"}>webmaster@romanh.de</a></small></li>
                                        </ul>

                                        <b className={"mt-2"}>Backend Developer</b>
                                        <ul className={"list-unstyled"}>
                                            <li><small><Icon icon={"address-card"} className={"mr-1"}/>Leon Krause</small></li>
                                        </ul>
                                    </div>
                                </Collapse>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </>
    )
}