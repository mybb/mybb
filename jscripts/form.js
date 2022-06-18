var Form = {
    /* Appends a form field to element of id */
    addFormField: function(id, fieldName, fieldType, fieldOptions) {
        var formFieldDiv = $('<div>', {
            class: 'row row--form field'
        });
        
        if (fieldType != 'select') {
            var input = $('<input>', {
                type: fieldType,
                name: fieldName,
                ...fieldOptions,
            });
            return $('#' + id).append(formFieldDiv.append(input));
        }

        var select = $('<select>', {
            name: fieldName,
            ...fieldOptions,
        });
        return $('#' + id).append(formFieldDiv.append(select));
    },
}
