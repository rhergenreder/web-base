import * as React from "react";
import Icon from "./icon";
import moment from "moment";

export class TokenList extends React.Component {

    constructor(props) {
        super(props);

        this.state = {
            api: props.api,
            tokens: null,
            alerts: []
        }
    }

    render() {

        let rows = [];
        if (this.state.tokens === null) {
            this.state.api.listTokens().then((res) => {
                this.setState({ ...this.state, tokens: res.tokens });
            });
        } else {
            for (const token of this.state.tokens) {
                const revoked = moment(token.valid_until).isSameOrBefore(new Date());
                rows.push(
                    <tr key={"token-" + token.uid} className={revoked ? "token-revoked" : ""}>
                        <td>{token.token}</td>
                        <td>{token.type}</td>
                        <td>{moment(token.valid_until).format("Do MMM YYYY, HH:mm")}</td>
                        <td>
                            <Icon icon={"times"} className={"clickable text-" + (revoked ? "secondary" : "danger")}
                                      onClick={() => (revoked ? null : this.onRevokeToken(token.token) )}
                                      disabled={revoked} />
                        </td>
                    </tr>
                );
            }
        }

        let alerts = [];
        let i = 0;
        for (const alert of this.state.alerts) {
            alerts.push(
              <div key={"alert-" + i++} className={"alert alert-" + alert.type}>
                  { alert.text }
              </div>
            );
        }

        return <>
            <h4>Tokens</h4>
            <table className={"table token-table"}>
                <thead>
                    <tr>
                        <th>Token</th>
                        <th>Type</th>
                        <th>Valid Until</th>
                        <th/>
                    </tr>
                </thead>
                <tbody>
                    { rows }
                </tbody>
            </table>
            <div>
                <button type={"button"} className={"btn btn-success m-2"}>
                    <Icon icon={"plus"} className={"mr-1"}/>
                    Create Token
                </button>
            </div>
            <div>
                { alerts }
            </div>
        </>;
    }

    onRevokeToken(token) {
        this.state.api.revokeToken(token).then((res) => {
            if (res.success) {
                let newTokens = this.state.tokens.slice();
                for (const tokenObj of newTokens) {
                    if (tokenObj.token === token) {
                        tokenObj.valid_until = moment();
                        break;
                    }
                }
                this.setState({ ...this.state, tokens: newTokens });
            } else {
                let newAlerts = this.state.alerts.slice();
                newAlerts.push({ type: "danger", text: res.msg });
                this.setState({ ...this.state, alerts: newAlerts });
            }
        });
    }
}