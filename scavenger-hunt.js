jQuery(document).ready(function($) {
    $(".scavenger-clue").click(function() {
        var clue_number = $(this).data("clue-number");
        var clue_content = $(this).data("content");
        var clue_element = $(this);
        
        // Add 'loading' class to clue element
        clue_element.addClass('loading');
        
        // AJAX request to server when a clue is clicked.
        $.ajax({
            type: "POST",
            url: scavenger_hunt_ajax.ajax_url,
            data: {
                action: "clue_clicked",
                clue_number: clue_number,
            },
            success: function(response) {
                // Remove 'loading' class upon AJAX success
                clue_element.removeClass('loading');
                
                if(response === 'success') {
                    // Create a modal/popup div to display the clue content
                    var popup = $('<div id="scavenger-popup" class="scavenger-popup"></div>');
                    var innerWrapper = $('<div class="scavenger-inner"></div>');
                    var closeButton = $('<button class="close-button">Done!</button>');

                    innerWrapper.append(clue_content).append(closeButton);
                    popup.append(innerWrapper);
                    $("body").append(popup);
                    
                    // Close the popup and remove the clue label when close button is clicked
                    closeButton.click(function() {
                        popup.remove();
                        clue_element.remove();
                    });
                } else if(response === 'invalid_clue_number') {
                    alert('Oops! Seems like you missed a clue.');
                } else {
                    alert('Something went wrong. Please try again.');
                }
            },
            error: function() {
                // Remove 'loading' class upon AJAX error
                clue_element.removeClass('loading');
                alert('Error occurred. Please try again.');
            }
        });
    });
});
