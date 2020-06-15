import * as React from "react";
import {Link} from "react-router-dom";
import Icon from "./elements/icon";

export default class View404 extends React.Component {
    render() {
        return <div className={"error-page"}>
            <h2 className={"headline text-warning"}>404</h2>
            <div className={"error-content"}>
                <h3>
                    <Icon icon={"exclamation-triangle"} classes={"text-warning"}/> Oops! Page not found.
                </h3>
                <p>
                    We could not find the page you were looking for.
                    Meanwhile, you may <Link to={"/admin/dashboard"}>return to dashboard</Link> or try using the search form.
                </p>
                <form className={"search-form"} onSubmit={(e) => e.preventDefault()}>
                    <div className={"input-group"}>
                        <input type={"text"} name={"search"} className={"form-control"} placeholder={"Search"} />
                        <div className={"input-group-append"}>
                            <button type="submit" name="submit" className={"btn btn-warning"}>
                                <Icon icon={"search"}/>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    }
}