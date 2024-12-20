# FocusHandler

This class is used to make it easier to preserve the focus state.
It is used to set the focus back to a given element after displaying content in a modal.

## Methods

* [saveFocusState](#saveFocusState)
* [resumeFocusState](#resumeFocusState)
* [saveFocusStatePersistent](#saveFocusStatePersistent)
* [resumeFocusStatePersistent](#resumeFocusStatePersistent)
* [setFocus](#setFocus)

### saveFocusState

Saves the current focus state under the given key.
If not explicitly set, the element that is currently in focus will be saved.
It is also possible to pass a selector (string) to search for a specific element during `resumeFocusState`.
This can be used when the original element reference is no longer available. E.g. due to DOM modifications.


#### Example
```JavaScript
const button = document.getElementById('gallery-button');

window.focusHandler.saveFocusState('image-gallery', button);
```

#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>focusHistoryKey</td>
        <td>string</td>
        <td>A key string under which the focus state should be saved.</td>
    <tr>
    <tr>
        <td>focusEl</td>
        <td>HTMLElement, string</td>
        <td>A reference to an HTML element or an element selector.</td>
    <tr>
</table>

### resumeFocusState

Resumes the focus to the element that was saved for the given key.



#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>focusHistoryKey</td>
        <td>string</td>
        <td></td>
    <tr>
    <tr>
        <td>focusOptions</td>
        <td>Object</td>
        <td></td>
    <tr>
</table>

### saveFocusStatePersistent

Saves the current focus state under the given key in the session storage.
By default, the given key will be prefixed with the `defaultStorageKeyPrefix` "sw-last-focus".
A unique selector is mandatory to resume the focus state on the correct element. (e.g. after a page reload)



#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>focusStorageKey</td>
        <td></td>
        <td></td>
    <tr>
    <tr>
        <td>uniqueSelector</td>
        <td></td>
        <td></td>
    <tr>
</table>

### resumeFocusStatePersistent

Resumes the focus to the element that was saved for the given key in the session storage.



#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>focusStorageKey</td>
        <td></td>
        <td></td>
    <tr>
    <tr>
        <td>focusOptions</td>
        <td></td>
        <td></td>
    <tr>
</table>

### setFocus

Tries to set the focus to the given element.



#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>el</td>
        <td>HTMLElement</td>
        <td></td>
    <tr>
    <tr>
        <td>focusOptions</td>
        <td>Object</td>
        <td></td>
    <tr>
</table>


