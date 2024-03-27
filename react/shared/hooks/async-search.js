import {useEffect, useState} from "react";


export default function useAsyncSearch(callback, minLength = 1) {

    const [searchString, setSearchString] = useState("");
    const [results, setResults] = useState(null);
    const [isSearching, setSearching] = useState(false);

    useEffect(() => {
        if (minLength > 0 && (!searchString || searchString.length < minLength)) {
            setResults([]);
            return;
        }

        if (!isSearching) {
            setSearching(true);
            callback(searchString).then(results => {
                setResults(results || null);
                setSearching(false);
            });
        }
    }, [searchString]);

    return [searchString, setSearchString, results];
}