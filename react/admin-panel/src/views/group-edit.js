import {useCallback, useEffect, useState} from "react";
import {useNavigate, useParams} from "react-router-dom";


export default function EditGroupView(props) {

    // const [groupId, setGroupId] = useState(props?.match?.groupId !== "new" ? parseInt(props.match.groupId) : null);

    const { groupId } = useParams();
    const navigate = useNavigate();

    const [fetchGroup, setFetchGroup] = useState(groupId !== "new");
    const [group, setGroup] = useState(null);

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

    return <></>

}