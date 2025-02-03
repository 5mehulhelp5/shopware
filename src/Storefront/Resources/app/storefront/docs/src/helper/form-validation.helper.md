# FormValidation

This class is a service to make HTML form validation easy.
It helps to implement all best-practices for accessible form validation.

The class is made available as a central instance at the window object.
Use the global instance to make use of the default form handling or create your own.

**Example**

```JavaScript
const form = document.getElementById('contact-form');
const invalidFields = window.formValidation.validateForm(form);
```

This service is used by the form handler plugin.
You can use the plugin to apply the full form handling to your form.
Use the associated data attribute to activate the plugin.

**Example**

```JavaScript
<form data-form-handler="true">
     <input type="email" data-validation="required,email">
</form>
```

To get the full set of best practices, you can use the form components
in Twig under `storefront/component/form` to render proper form fields in your form.
You can use them via `{% sw_include }%` to import the template.

**Example**

```JavaScript
{% sw_include '@Storefront/storefront/component/form/form-input.html.twig' with {
    label: 'account.personalFirstNameLabel'|trans|sw_sanitize,
    id: 'personalFirstName',
    name: 'firstName',
    value: data.get('firstName'),
    autocomplete: 'section-personal given-name',
    violationPath: violationPath,
    validationRules: 'required',
    additionalClass: 'col-sm-6',
} %}
```

## Methods

* [addValidator](#addValidator)
* [addErrorMessage](#addErrorMessage)
* [setConfig](#setConfig)
* [validateForm](#validateForm)
* [validateField](#validateField)
* [validateRequired](#validateRequired)
* [validateEmail](#validateEmail)
* [validateConfirmation](#validateConfirmation)
* [validateMinLength](#validateMinLength)
* [setFieldValid](#setFieldValid)
* [setFieldInvalid](#setFieldInvalid)
* [setFieldNeutral](#setFieldNeutral)
* [setFieldRequired](#setFieldRequired)
* [setFieldNotRequired](#setFieldNotRequired)
* [setFieldValidationMessage](#setFieldValidationMessage)
* [resetFieldFeedback](#resetFieldFeedback)
* [checkVisibility](#checkVisibility)
* [setNoValidate](#setNoValidate)
* [isFormElement](#isFormElement)

### addValidator

Add a validator rule that can be used for form validation.

**Returns:** boolean


#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>validatorName</td>
        <td>string</td>
        <td>The technical name under which the validation rule is tracked.</td>
    <tr>
    <tr>
        <td>validationFunction</td>
        <td>function</td>
        <td>The function that does the validation of the field value.</td>
    <tr>
    <tr>
        <td>errorMessage</td>
        <td>string</td>
        <td>The validation message that should be shown if the validation fails.</td>
    <tr>
</table>

### addErrorMessage

Add an error message for a matching validator.

**Returns:** boolean


#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>validatorName</td>
        <td>string</td>
        <td>The technical name of the validator the error message belongs to.</td>
    <tr>
    <tr>
        <td>errorMessage</td>
        <td>string</td>
        <td>The content of the error message.</td>
    <tr>
</table>

### setConfig

Set a config property of the form validation.



#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>key</td>
        <td>string</td>
        <td></td>
    <tr>
    <tr>
        <td>value</td>
        <td>any</td>
        <td></td>
    <tr>
</table>

### validateForm

Validates all fields of a form with their individual validation config.
Returns an array of all invalid fields.

**Returns:** boolean,Array.&lt;HTMLElement&gt;


#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>form</td>
        <td>HTMLFormElement</td>
        <td></td>
    <tr>
    <tr>
        <td>formFields</td>
        <td>NodeList</td>
        <td></td>
    <tr>
</table>

### validateField

Validates a single field based on its validation rules.
You can define the rules via the data attribute `data-validation`.
Add a comma separated list of validator names in the attribute.
The validators and associated messages are prioritized by their order.
The first validator has the highest priority and so forth.
Some validators might access additional attributes of the field,
like the `minlength` attribute for the "minLength" validator.

**Returns:** boolean,Array.&lt;string&gt;

#### Example
```JavaScript
<input type="email" data-validation="required,email">
```

#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>field</td>
        <td>HTMLElement</td>
        <td></td>
    <tr>
</table>

### validateRequired

Checks if the value is not empty.

**Returns:** boolean


#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>value</td>
        <td>string</td>
        <td></td>
    <tr>
    <tr>
        <td>field</td>
        <td>HTMLElement</td>
        <td></td>
    <tr>
</table>

### validateEmail

Checks if the value is a valid email address.

**Returns:** boolean


#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>value</td>
        <td>string</td>
        <td></td>
    <tr>
</table>

### validateConfirmation

Checks if the confirmation field is equal to the main field.
The validator works based on the ID naming of the field.
The confirmation field should have the same ID as the original field,
but with the suffix "Confirmation" to it.



#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>value</td>
        <td>string</td>
        <td></td>
    <tr>
    <tr>
        <td>field</td>
        <td>HTMLElement</td>
        <td></td>
    <tr>
</table>

### validateMinLength

Checks the value for a minimum length.
If the field has a minlength attribute it will be checked against the value of the attribute,
otherwise the `defaultMinLength` config will be used.

**Returns:** boolean


#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>value</td>
        <td>string</td>
        <td></td>
    <tr>
    <tr>
        <td>field</td>
        <td>HTMLElement</td>
        <td></td>
    <tr>
</table>

### setFieldValid

Sets the field status to valid.
Applies all necessary styles and attributes.



#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>field</td>
        <td>HTMLElement</td>
        <td></td>
    <tr>
</table>

### setFieldInvalid

Sets the field status to invalid.
Applies all necessary styles and attributes.



#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>field</td>
        <td>HTMLElement</td>
        <td></td>
    <tr>
    <tr>
        <td>validationErrors</td>
        <td></td>
        <td></td>
    <tr>
</table>

### setFieldNeutral

Sets the field status to neutral.
Applies all necessary styles and attributes.



#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>field</td>
        <td>HTMLElement</td>
        <td></td>
    <tr>
</table>

### setFieldRequired

Sets a form field to be required.
It sets all necessary attributes and updates the corresponding label.



#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>field</td>
        <td>HTMLElement</td>
        <td></td>
    <tr>
</table>

### setFieldNotRequired

Sets a form field to be not required.
It removes all necessary attributes and updates the corresponding label.



#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>field</td>
        <td>HTMLElement</td>
        <td></td>
    <tr>
</table>

### setFieldValidationMessage

Sets the validation message within the feedback text of the form field.
Only the error message with the highest validation priority will be shown.

**Returns:** boolean


#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>field</td>
        <td>HTMLElement</td>
        <td></td>
    <tr>
    <tr>
        <td>validationErrors</td>
        <td>Array.&lt;string&gt;</td>
        <td></td>
    <tr>
</table>

### resetFieldFeedback

Restes the form field feedback text.

**Returns:** boolean


#### Parameters
<table>
    <tr>
        <th>Param</th>
        <th>Type</th>
        <th>Description</th>
    </tr>
    <tr>
        <td>field</td>
        <td>HTMLElement</td>
        <td></td>
    <tr>
</table>

### checkVisibility

Checks if an HTML element is visible within the page.

**Returns:** boolean


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
</table>

### setNoValidate

Sets the `novalidate` attribute on a form.
It is used to disable the standard browser validation.
Standard browser validation is seen as not accessible enough.
This is why the custom validation is applied.



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
</table>

### isFormElement

Checks if the provided element is a form element.

**Returns:** boolean


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
</table>


