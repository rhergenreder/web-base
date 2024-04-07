import {Autocomplete, TextField} from "@mui/material";
import useAsyncSearch from "../hooks/async-search";


export default function SearchField(props) {

    const { onSearch, onSelect, ...other } = props;

    const [searchString, setSearchString, results] = useAsyncSearch(props.onSearch, 3);

    return <Autocomplete {...other}
         options={Object.values(results ?? {})}
         onChange={(e, n) => onSelect(n)}
         renderInput={(params) => (
             <TextField
                 {...params}
                 value={searchString}
                 onChange={e => setSearchString(e.target.value)}
                 label={"Search input"}
                 InputProps={{
                     ...params.InputProps,
                     type: 'search',
                 }}
             />
         )}
    />;
}