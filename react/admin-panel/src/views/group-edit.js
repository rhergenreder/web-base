import {useCallback, useContext, useEffect, useState} from "react";
import {Link, useNavigate, useParams} from "react-router-dom";
import {LocaleContext} from "shared/locale";
import {CircularProgress} from "@material-ui/core";
import * as React from "react";
import ColorPicker from "material-ui-color-picker";

const defaultGroupData = {
    name: "",
    color: "#ccc",
    members: []
};

export default function EditGroupView(props) {

    const {translate: L, requestModules, currentLocale} = useContext(LocaleContext);

    const { groupId } = useParams();
    const navigate = useNavigate();
    const isNewGroup = groupId === "new";

    const [fetchGroup, setFetchGroup] = useState(!isNewGroup);
    const [group, setGroup] = useState(isNewGroup ? defaultGroupData : null);

    const onFetchGroup = useCallback((force = false) => {
        if (force || fetchGroup) {
            setFetchGroup(false);
            props.api.getGroup(groupId).then(res => {
               if (!res.success) {
                   props.showDialog(res.msg, "Error fetching group");
                   navigate("/admin/groups");
               } else {
                   setGroup(res.group);
               }
            });
        }
    }, []);

    useEffect(() => {
        onFetchGroup();
    }, []);

    if (group === null) {
        return <CircularProgress />
    }

    return <>
        <div className={"content-header"}>
            <div className={"container-fluid"}>
                <div className={"row mb-2"}>
                    <div className={"col-sm-6"}>
                        <h1 className={"m-0 text-dark"}>
                            { isNewGroup ? L("account.new_group") : L("account.group") + ": " + group.name }
                        </h1>
                    </div>
                    <div className={"col-sm-6"}>
                        <ol className={"breadcrumb float-sm-right"}>
                            <li className={"breadcrumb-item"}><Link to={"/admin/dashboard"}>Home</Link></li>
                            <li className="breadcrumb-item active"><Link to={"/admin/groups"}>Group</Link></li>
                            <li className="breadcrumb-item active">{ isNewGroup ? L("general.new") : groupId }</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <div className={"content"}>
            <div className={"row"}>
                <div className={"col-6 pl-5 pr-5"}>
                    <form role={"form"} onSubmit={(e) => this.submitForm(e)}>
                        <div className={"form-group"}>
                            <label htmlFor={"name"}>Group Name</label>
                            <input type={"text"} className={"form-control"} placeholder={"Name"}
                                   name={"name"} id={"name"} maxLength={32} value={group.name}/>
                        </div>

                        <div className={"form-group"}>
                            <label htmlFor={"color"}>Color</label>
                            <div>
                                <ColorPicker
                                    value={group.color}
                                    size={"small"}
                                    variant={"outlined"}
                                    style={{ backgroundColor: group.color }}
                                    floatingLabelText={group.color}
                                    onChange={color => setGroup({...group, color: color})}
                                />
                            </div>
                        </div>

                        <Link to={"/admin/groups"} className={"btn btn-info mt-2 mr-2"}>
                            &nbsp;Back
                        </Link>
                        <button type={"submit"} className={"btn btn-primary mt-2"}>Submit</button>
                    </form>
                </div>
                <div className={"col-6"}>
                    <h3>Members</h3>
                </div>
            </div>
        </div>
    </>

}