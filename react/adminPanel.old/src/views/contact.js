import * as React from "react";
import {Link} from "react-router-dom";
import Alert from "../elements/alert";
import ReactTooltip from "react-tooltip";
import {useState} from "react";

export default function ContactRequestOverview(props) {

    let parent = {
        api: props.api,
        contactRequests: props.contactRequests || [ ]
    };

    let [errors, setErrors] = useState([]);

    function removeError(i) {
        if (i >= 0 && i < errors.length) {
            let newErrors = errors.slice();
            newErrors.splice(i, 1);
            setErrors(newErrors);
        }
    }

    console.log("contact site", parent.contactRequests);

    let errorElements = [];
    for (let i = 0; i < errors.length; i++) {
        errorElements.push(<Alert key={"error-" + i} onClose={() => removeError(i)} {...errors[i]}/>)
    }

    let chats = [];
    for (let i = 0; i < parent.contactRequests.length; i++) {
        const req = parent.contactRequests[i];
        chats.push(<div key={"contact-request-" + i}>
            From: { req.from_name } - { req.from_email }
            Unread messages: { req.unread }
        </div>
        );
    }

    return <>
        <div className="content-header">
            <div className="container-fluid">
                <div className="row mb-2">
                    <div className="col-sm-6">
                        <h1 className="m-0 text-dark">Contact Requests</h1>
                    </div>
                    <div className="col-sm-6">
                        <ol className="breadcrumb float-sm-right">
                            <li className="breadcrumb-item"><Link to={"/admin/dashboard"}>Home</Link></li>
                            <li className="breadcrumb-item active">Contact</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div className={"content"}>
            {errorElements}
            {chats}
        </div>
        <ReactTooltip />
    </>;
}