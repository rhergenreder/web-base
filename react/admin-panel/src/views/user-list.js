import {Link, Navigate, useNavigate} from "react-router-dom";
import {useCallback, useContext, useEffect} from "react";
import {LocaleContext} from "shared/locale";
import {DataColumn, DataTable, NumericColumn, StringColumn} from "shared/elements/data-table";
import {Button, IconButton} from "@material-ui/core";
import EditIcon from '@mui/icons-material/Edit';
import {Chip} from "@mui/material";
import AddIcon from "@mui/icons-material/Add";


export default function UserListView(props) {

    const {translate: L, requestModules, currentLocale} = useContext(LocaleContext);
    const navigate = useNavigate();

    useEffect(() => {
        requestModules(props.api, ["general", "account"], currentLocale).then(data => {
            if (!data.success) {
                props.showDialog("Error fetching translations: " + data.msg);
            }
        });
    }, [currentLocale]);

    const onFetchUsers = useCallback(async (page, count, orderBy, sortOrder) => {
        let res = await props.api.fetchUsers(page, count, orderBy, sortOrder);
        if (res.success) {
            return Promise.resolve([res.users, res.pagination]);
        } else {
            props.showDialog(res.msg, "Error fetching users");
            return null;
        }
    }, []);

    const groupColumn = (() => {
       let column = new DataColumn(L("account.groups"), "groups");
       column.renderData = (L, entry) => {
           return Object.values(entry.groups).map(group => <Chip key={"group-" + group.id} label={group.name}/>)
       }
       return column;
    })();

    const actionColumn = (() => {
        let column = new DataColumn(L("general.actions"), null, false);
        column.renderData = (L, entry) => <>
            <IconButton size={"small"} title={L("general.edit")} onClick={() => navigate("/admin/user/" + entry.id)}>
                <EditIcon />
            </IconButton>
        </>
        return column;
    })();

    const columnDefinitions = [
        new NumericColumn(L("general.id"), "id"),
        new StringColumn(L("account.username"), "name"),
        new StringColumn(L("account.email"), "email"),
        groupColumn,
        new StringColumn(L("account.full_name"), "full_name"),
        actionColumn,
    ];

    return <>
        <div className={"content-header"}>
            <div className={"container-fluid"}>
                <div className={"row mb-2"}>
                    <div className={"col-sm-6"}>
                        <h1 className={"m-0 text-dark"}>Users</h1>
                    </div>
                    <div className={"col-sm-6"}>
                        <ol className={"breadcrumb float-sm-right"}>
                            <li className={"breadcrumb-item"}><Link to={"/admin/dashboard"}>Home</Link></li>
                            <li className="breadcrumb-item active">Users</li>
                        </ol>
                    </div>
                </div>
            </div>
            <div className={"content"}>
                <div className={"container-fluid"}>
                    <Link to="/admin/user/new">
                        <Button variant={"outlined"} startIcon={<AddIcon />} size={"small"}>
                            {L("general.create_new")}
                        </Button>
                    </Link>
                    <DataTable className={"table table-striped"}
                               fetchData={onFetchUsers}
                               placeholder={"No users to display"} columns={columnDefinitions} />
                </div>
            </div>
        </div>
    </>
}