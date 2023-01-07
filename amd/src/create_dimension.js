define(['core/ajax'], function(ajax) {
    function createDimension(name, description, parentid) {
        // Set up the parameters for the web service call
        const params = {
            'name': name,
            'description': description,
            'parentid': parentid
        };

        // Set up the AJAX request
        const request = {
            methodname: 'local_catquiz_create_dimension',
            args: params
        };

        // Make the web service call
        ajax.call([request])[0].then((response) => {
            // The web service call was successful
            const id = response.id;
            // Do something with the ID of the newly created dimension
        }).catch((ex) => {
            // There was an error - do something here
        });
    }

    // Return the createDimension function so it can be used by other parts of the code
    return {
        createDimension: createDimension
    };
});