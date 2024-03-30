import {Link, useNavigate} from "react-router-dom";
import {useCallback, useContext, useEffect, useState} from "react";
import {LocaleContext} from "shared/locale";
import {
    BoolColumn,
    ControlsColumn,
    DataColumn,
    DataTable, DateTimeColumn,
    NumericColumn,
    StringColumn
} from "shared/elements/data-table";
import {Button} from "@material-ui/core";
import EditIcon from '@mui/icons-material/Edit';
import {Chip} from "@mui/material";
import AddIcon from "@mui/icons-material/Add";
import usePagination from "shared/hooks/pagination";


export default function UserListView(props) {

    const api = props.api;
    const showDialog = props.showDialog;
    const {translate: L, requestModules, currentLocale} = useContext(LocaleContext);
    const navigate = useNavigate();
    const pagination = usePagination();
    const [users, setUsers] = useState([]);

    useEffect(() => {
        requestModules(props.api, ["general", "account"], currentLocale).then(data => {
            if (!data.success) {
                props.showDialog("Error fetching translations: " + data.msg);
            }
        });
    }, [currentLocale]);

    const onFetchUsers = useCallback((page, count, orderBy, sortOrder) => {
        api.fetchUsers(page, count, orderBy, sortOrder).then((res) => {
            if (res.success) {
                setUsers(res.users);
                pagination.update(res.pagination);
            } else {
                showDialog(res.msg, "Error fetching users");
                return null;
            }
        });
    }, [api, showDialog]);

    const groupColumn = (() => {
       let column = new DataColumn(L("account.groups"), "groups");
       column.renderData = (L, entry) => {
           return Object.values(entry.groups).map(group => <Chip
               key={"group-" + group.id}
               style={{ backgroundColor: group.color }}
               label={group.name} />
           )
       }
       return column;
    })();

    const columnDefinitions = [
        new NumericColumn(L("general.id"), "id"),
        new StringColumn(L("account.username"), "name"),
        new StringColumn(L("account.full_name"), "fullName"),
        new StringColumn(L("account.email"), "email"),
        groupColumn,
        new DateTimeColumn(L("account.registered_at"), "registeredAt"),
        new BoolColumn(L("account.active"), "active", { align: "center" }),
        new BoolColumn(L("account.confirmed"), "confirmed", { align: "center" }),
        new ControlsColumn(L("general.controls"), [
            { label: L("general.edit"), element: EditIcon, onClick: (entry) => navigate(`/admin/user/${entry.id}`) }
        ]),
    ];

    return <div className={"content-header"}>
        <div className={"container-fluid"}>
            <ol className={"breadcrumb"}>
                <li className={"breadcrumb-item"}><Link to={"/admin/dashboard"}>Home</Link></li>
                <li className="breadcrumb-item active">Users</li>
            </ol>
        </div>
        <div className={"content"}>
            <div className={"container-fluid"}>
                <DataTable
                    data={users}
                    pagination={pagination}
                    defaultSortOrder={"asc"}
                    defaultSortColumn={0}
                    className={"table table-striped"}
                    fetchData={onFetchUsers}
                    placeholder={"No users to display"}
                    title={L("account.users")}
                    columns={columnDefinitions} />
                <Link to="/admin/user/new">
                    <Button variant={"outlined"} startIcon={<AddIcon />} size={"small"}>
                        {L("general.create_new")}
                    </Button>
                </Link>
            </div>
        </div>
    </div>
}