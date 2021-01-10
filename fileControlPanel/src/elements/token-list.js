import * as React from "react";
import Icon from "./icon";

export class TokenList extends React.Component {

    constructor(props) {
        super(props);

        this.state = {
            api: props.api,
            tokens: null
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
                rows.push(
                    <tr key={"token-" + token.uid}>
                        <td>{token.token}</td>
                        <td>{token.type}</td>
                        <td>{token.valid_until}</td>
                        <td><Icon icon={"times"} className={"text-danger"}/></td>
                    </tr>
                );
            }
        }

        return <>
            <h4>Tokens</h4>
            <table className={"table"}>
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
        </>;
    }
}