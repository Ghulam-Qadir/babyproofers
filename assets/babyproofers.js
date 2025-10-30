jQuery(document).ready(function($) {
    // Loader initialization
    const loaderHTML = `
    <style>
    .loader-wrapper {
    	display: none;
    }
    #loader {
    	position: fixed;
    	top: 0;
    	left: 0;
    	width: 100%;
    	height: 100%;
    	background: #f9fdf9;
    	display: flex;
    	justify-content: center;
    	align-items: center;
    	z-index: 9999;
    }
    #loader img {
    	width: 80px;
    	height: auto;
    	animation: bounce 1s infinite ease-in-out;
    }
    @keyframes bounce {
    	0%, 100% { transform: translateY(0); }
    	50% { transform: translateY(-25px); }
    }
    </style>
    <div class="loader-wrapper">
    <div id="loader">
    <img src="https://babyproofers.com.au/wp-content/uploads/2025/05/site-icon.png" alt="Loading...">
    </div>
    </div>`;
    
    $('footer').append(loaderHTML);

    // Helper functions
    function decodeHTML(html) {
    	const textarea = document.createElement('textarea');
    	textarea.innerHTML = html;
    	return textarea.value;
    }

    // Select2 initialization with available options
    function initSelect2WithAvailable() {
    	$('select[multiple]').each(function() {
    		$(this).select2({
    			dropdownParent: $('body'),
    			templateResult: function(data) {
                    if (!data.id) return data.text; // keep placeholder
                    const $option = $(data.element);
                    if (!$option.hasClass('available') && !$option.attr('data-available')) {
                        return null; // hide unavailable options
                    }
                    return data.text;
                }
            });
    	});
    }
    
    initSelect2WithAvailable();

    // Dropdown loading functionality
    function loadDropdown({ action, data, targetDropdownSelector, loadingClass = 'gf-loading', onAfterLoad = null }) {
    	const $targetDropdown = $(targetDropdownSelector);

    	$.ajax({
    		url: var_babyproofers.url,
    		type: 'POST',
    		dataType: 'json',
    		data: {
    			action: action,
    			formdata: JSON.stringify(data)
    		},
    		beforeSend: function() {
    			$(".loader-wrapper").show();
    			$targetDropdown.addClass(loadingClass);
    		},
    		success: function(response) {
    			$(".loader-wrapper").hide();
    			if (Array.isArray(response) && response.length > 0) {
                    // Hide all, clear flags
                    $targetDropdown.find('option').each(function() {
                    	$(this).hide().removeClass('available').removeAttr('data-available');
                    });
                    
                    // Show valid & mark available
                    response.forEach(function(item) {
                    	$targetDropdown.find(`option[value="${item.id}"]`)
                    	.show()
                    	.addClass('available')
                    	.attr('data-available', '1');
                    });
                    
                    if (typeof onAfterLoad === 'function') {
                    	onAfterLoad(response);
                    }
                    
                    $targetDropdown.trigger('change.select2');
                    } else {
                    // Nothing valid → clear all
                    $targetDropdown.val(null).trigger('change.select2');
                }
            },
            complete: function() {
            	$targetDropdown.removeClass(loadingClass);
            }
        });
    }

    // Category loader initialization
    function initCategoryLoader({ parentInputSelector, parentDropdownSelector, childDropdownSelector, wrapperSelector }) {
    	const $parentInputs = $(`${parentInputSelector} input`);
    	const $parentDropdown = $(parentDropdownSelector);
    	const $childDropdown = $(childDropdownSelector);

        // Event handlers
        function handleParentInputClick() {
        	const parentValue = $(this).val();
        	if ($(this).is(':checked')) {
        		loadDropdown({
        			action: 'get_product_cat',
        			data: parentValue,
        			targetDropdownSelector: parentDropdownSelector
        		});

        		if (wrapperSelector) {
        			$(`${wrapperSelector} h2`).text(parentValue);
        		}
        		} else {
                // Parent input unchecked → clear both
                $parentDropdown.val(null).trigger('change.select2');
                $childDropdown.val(null).trigger('change.select2');
                if (wrapperSelector) {
                	$(`${wrapperSelector} h2`).text('');
                }
            }
        }

        function handleParentDropdownChange() {
        	const parentValue = $parentInputs.filter(':checked').val();
        	const selectedCategory = $parentDropdown.val();

        	if (selectedCategory) {
                // Load child, and only remove invalid selected options
                loadDropdown({
                	action: 'get_product_cat_child',
                	data: { id: selectedCategory, room: parentValue },
                	targetDropdownSelector: childDropdownSelector,
                	onAfterLoad: function(validOptions) {
                		const validIds = validOptions.map(item => String(item.id));
                		let currentSelected = $childDropdown.val() || [];
                		if (!Array.isArray(currentSelected)) {
                			currentSelected = [currentSelected];
                		}
                        // Keep only selected values that are still valid
                        const newSelected = currentSelected.filter(val => validIds.includes(val));
                        $childDropdown.val(newSelected).trigger('change.select2');
                    }
                });
                } else {
                // If no parent selected → clear child completely
                $childDropdown.val(null).trigger('change.select2');
            }
        }

        // Set up event listeners
        $parentInputs.off('.categoryLoader').on('click.categoryLoader', handleParentInputClick);
        $parentDropdown.off('.categoryLoader').on('change.categoryLoader', handleParentDropdownChange);

        // Auto-load parent on page load
        const $checkedInput = $parentInputs.filter(':checked');
        if ($checkedInput.length) {
        	const parentValue = $checkedInput.val();
        	loadDropdown({
        		action: 'get_product_cat',
        		data: parentValue,
        		targetDropdownSelector: parentDropdownSelector
        	});

        	if (wrapperSelector) {
        		$(`${wrapperSelector} h2`).text(parentValue);
        	}

        	const selectedCategory = $parentDropdown.val();
        	if (selectedCategory) {
        		loadDropdown({
        			action: 'get_product_cat_child',
        			data: { id: selectedCategory, room: parentValue },
        			targetDropdownSelector: childDropdownSelector
        		});
        	}
        }
    }

    // Room page loading
    function loadRoomPage() {
    	$('.room-select-page, .next-room-wraper').each(function(index, el) {
    		const $page = $(this);

    		setTimeout(function() {
                if ($page[0].style.display === 'none') return; // skip hidden inline
                
                const $roomType = $page.find('.room-box-select .ginput_container .gfield_radio');
                const $parentCat = $page.find('.room-parent-cat select.custom-class');
                const $subCat = $page.find('.room-sub-cat select.custom-class');
                const $catWrapper = $page.find('.cat-wraper');

                initCategoryLoader({
                	parentInputSelector: `#${$roomType.attr('id')}`,
                	parentDropdownSelector: `#${$parentCat.attr('id')}`,
                	childDropdownSelector: `#${$subCat.attr('id')}`,
                	wrapperSelector: `#${$catWrapper.attr('id')}`
                });
            }, 200);
    	});
    }

    // Checkbox exclusion logic
    function isExcluded(element) {
    	const excludedClasses = ['bedroom', 'bathroom', 'livingRoom', 'diningroom'];
    	const $el = $(element);

    	return excludedClasses.some(cls => $el.hasClass(cls));
    }


    function getRoomBoxIds() {
    	var ids = [];
    	jQuery('.next-room-wraper .room-box-select').each(function(i, el) {
        var id = jQuery(el).attr('id');       // e.g., field_4_1054
        var lastSegment = id.split('_').pop(); // get last part
        ids.push(lastSegment);
    });
    ids.unshift('1178'); // add 1178 to the beginning
    return ids;        // return the whole array
}




function smoothScrollTo(element, duration = 1000) {
	const $target = $(element);
	if (!$target.length) return;

	// Fixed offset from top (e.g. sticky header height + padding)
	const topOffset = 130;

	const targetPosition = $target.offset().top - topOffset;
	const startPosition = $(window).scrollTop();

	// Only scroll down
	if (targetPosition <= startPosition) return;

	const distance = targetPosition - startPosition;
	let startTime = null;

	function animation(currentTime) {
		if (!startTime) startTime = currentTime;
		const timeElapsed = currentTime - startTime;
		const scrollAmount = easeInOutQuad(timeElapsed, startPosition, distance, duration);
		$(window).scrollTop(scrollAmount);
		if (timeElapsed < duration) {
			requestAnimationFrame(animation);
		}
	}

	function easeInOutQuad(t, b, c, d) {
		t /= d / 2;
		if (t < 1) return c / 2 * t * t + b;
		t--;
		return -c / 2 * (t * (t - 2) - 1) + b;
	}

	requestAnimationFrame(animation);
}






function setupCheckboxStates() {
	// Gravity Form ID
	const formId = '#gform_4';

	// Your specific checkbox field IDs
	const checkboxFieldIDs = getRoomBoxIds();

	// Collect all checkboxes in these fields
	let $checkboxes = $();
	checkboxFieldIDs.forEach(function(id) {
		$checkboxes = $checkboxes.add($(`#input_4_${id} .gchoice input`));
	});

	// Update checkbox states
	function updateCheckboxStates(e) {
		const checkedOptions = {};

		// Collect checked option values
		$checkboxes.each(function () {
			if ($(this).is(':checked')) {
				checkedOptions[$(this).val()] = true;
			}
		});

		// Disable duplicates except the currently checked one
		$checkboxes.each(function () {
			const $checkbox = $(this);
			$checkbox.prop('disabled', checkedOptions[$checkbox.val()] && !$checkbox.is(':checked'));
		});

		// ✅ Only scroll if triggered by an event (e.g., a change)
		if (e) {
			const $target = $(e.target);
			const wrapper = $target
			.closest('.room-box-select')
			.closest('.gform_page')
			.find('.drop-down-main-wraper');

			smoothScrollTo(wrapper, 1500);
		}
	}

	// Initialize and bind event
	$checkboxes.on('change', updateCheckboxStates);

	// Run once to initialize state (no scroll)
	updateCheckboxStates();
}



function preselectedroompage(id){
	let roomWraper = jQuery(`#gform_page_4_${id}`)
	let roomboxinput = jQuery(roomWraper).find('.room-box-select').find('input[type="radio"]').filter(':checked').val();
	if(roomboxinput != undefined){
		console.log('preselectedroompage', roomboxinput)
		let wraper =  jQuery(roomWraper).find('.room-box-select').next('.drop-down-main-wraper');
		smoothScrollTo(wraper, 1500)
	}

}


    // Room-specific checkbox disabling
    function updateRoomCheckboxStates() {
    	$('.room-box-select.livingRoom input:not(input[value="Living Room"])').prop('disabled', true);
    	$('.room-box-select.diningroom input:not(input[value="Dining Room"])').prop('disabled', true);
    	$('.room-box-select.bathroom input:not(input[value="Bathroom"])').prop('disabled', true);
    	$('.room-box-select.bedroom input:not(input[value="Bedroom"])').prop('disabled', true);
    }

    // Load more functionality
    function setupLoadMore() {
    	const loadCount = 12;

    	$('.gform_page').each(function() {
    		const $page = $(this);
    		const $items = $page.find('.product .ginput_container_checkbox .gfield_checkbox .gchoice');
    		const $button = $page.find('button.product');

    		if ($items.length <= loadCount) {
    			$items.addClass('active');
    			$button.hide();
    			return;
    		}

            // Show initial items
            $items.slice(0, loadCount).addClass('active');
            
            // Handle button click
            $button.on('click', function(e) {
            	e.preventDefault();
            	const $hidden = $items.filter(':not(.active)').slice(0, loadCount);
            	$hidden.addClass('active');

            	if ($items.filter(':not(.active)').length === 0) {
            		$button.hide();
            	}
            });
        });
    }



    function nextButtonTextChange(roomTypePageClassList){

    	switch (roomTypePageClassList) {
    		case 'bedroom':
    		return "Next Bedroom"
    		break;
    		case 'bathroom':
    		return "Next Bathroom"
    		break;
    		case 'livingroom':
    		return "Next Living Room"
    		break;
    		case 'diningroom':
    		return "Next Dining Room"
    		break;
    		default:
    		return "Next Room"
    		break;
    	}	

    }


    function bedroomSetupNextButtonText(id) {
    	$('.gform_page.bedroom').each(function (i, el) {
    		const $page = $(this);
    		const currentPageId = $(`#gform_page_4_${id}`).attr('id');
    		const $fieldset = $page.find('fieldset.add_next');
    		const $nextButton = $page.find('input.gform_next_button');
    		const $radios = $fieldset.find('input[type="radio"]');

		// Function to update button text
		const updateButtonText = () => {
			const isVisible = $fieldset.attr('data-conditional-logic') === 'visible';
			const buttonTextChange = (isVisible) ? "Room" : "Bedroom";
			const checkedVal = $radios.filter(':checked').val();

			$nextButton.val(
				isVisible && (checkedVal === 'No' || checkedVal === undefined)
				? "I'm Finished?"
				: `Next ${buttonTextChange}`
			);
		};

		// Initialize on load
		updateButtonText();

		// Update on radio change
		$radios.on('change', updateButtonText);

		// Update when conditional logic may affect visibility
		$(document).on('gform_post_conditional_logic', updateButtonText);
	});
    }




    function nextRoomWraperNextButtonText() {
    	$('.gform_page.next-room-wraper').each(function () {
    		const $page = $(this);
    		const $fieldset = $page.next().find('fieldset.add_next');
    		const $nextButton = $fieldset.closest('.gform_page').find('input.gform_next_button');
    		const $radios = $fieldset.find('input[type="radio"]');

    // Quantity fields mapping
    const qtyFields = {
    	'Bedroom': $page.find('.hmbedroom'),
    	'Bathroom': $page.find('.hmbathroom'),
    	'Living Room': $page.find('.hmlivingroom'),
    	'Dining Room': $page.find('.hmdiningroom')
    };

    // This function recalculates text on every call
    const updateButtonText = () => {
      // Check what room type is currently selected
      const selectedRoom = $page.find('.room-box-select input:checked').val();
      const isRecognizedRoom = ['Bedroom', 'Bathroom', 'Living Room', 'Dining Room'].includes(selectedRoom);

      let buttonTextUpdate = 'Room'; // default

      if (isRecognizedRoom) {
        // Check the quantity field for the selected room
        const $qtyField = qtyFields[selectedRoom];
        if ($qtyField.length) {
        	const qtyVal = $qtyField.find('input:checked').val();
        	const isVisible = $qtyField.attr('data-conditional-logic') === 'visible';
          // Logic based on quantity
          if (isVisible && qtyVal && qtyVal !== '1') {
          	buttonTextUpdate = selectedRoom;
          	} else {
            // even if recognized, use generic Room if qty is 1
            buttonTextUpdate = 'Room';
        }
        } else {
          buttonTextUpdate = selectedRoom; // fallback
      }
  }

      // Now apply conditional logic for next button
      const isVisible = $fieldset.attr('data-conditional-logic') === 'visible';
      const checkedVal = $radios.filter(':checked').val();
      $nextButton.val(
      	isVisible && (checkedVal === 'No' || checkedVal === undefined)
      	? "I'm Finished?"
      	: `Next ${buttonTextUpdate}`
      );
  };

    // Initialize and bind events
    updateButtonText();
    // when radio inside add_next changes
    $radios.on('change', updateButtonText);
    // when quantity radios change
    $page.find('.hmbedroom input, .hmbathroom input, .hmlivingroom input, .hmdiningroom input').on('change', updateButtonText);
    // when room type changes
    $page.find('.room-box-select input').on('change', updateButtonText);
    // also when conditional logic triggers
    $(document).on('gform_post_conditional_logic', updateButtonText);
});
    }




    // Next button text updating
    function setupNextButtonText() {
    	$('.gform_page:not(".next-room-wraper"):not(".bedroom")').each(function(i, el) {
    		const $page = $(this);
    		const $fieldset = $(this).find('fieldset.add_next');
    		const roomTypePageClassList = jQuery($page).closest('.gform_page').attr("class").split(/\s+/).filter(c => c !== 'gform_page')[0] || null;
    		const $nextButton = $fieldset.closest('.gform_page').find('input.gform_next_button');
    		const $radios = $fieldset.find('input[type="radio"]');
    		

        // Function to update button text
        const updateButtonText = () => {

        	const isVisible = $fieldset.attr('data-conditional-logic') === 'visible';
        	const checkedVal = $radios.filter(':checked').val();
        	$nextButton.val(
        		isVisible && (checkedVal === 'No' || checkedVal === undefined)
        		? "I'm Finished?"
        		: `Next Room`
        	);
        };

        // Initialize on load
        updateButtonText();
        
        // Update on radio change
        $radios.on('change', updateButtonText);
        
        // Also update when conditional logic might change visibility
        $(document).on('gform_post_conditional_logic', updateButtonText);
    });
    }

    function setupPagination(formId) {
        let wasStepClicked = false;

        function bindStepClicks($form) {
        // Make steps clickable again
        $form.find('.gf_step').each(function(index) {
            const $step = $(this);
            $step.css('cursor', 'pointer').attr('data-step', index + 1);
        });

        $form.find('.gf_step').off('click').on('click', function () {
            const targetStep = parseInt($(this).attr('data-step'));
            if (isNaN(targetStep)) return;

            $form.find('input[name="gform_target_page_number_' + formId + '"]').val(targetStep);
            wasStepClicked = true;
            $form.trigger('submit', [true]); // Submit with step jump
        });
    }

    const $form = $('#gform_' + formId);
    if (!$form.length) return;

    // Initial setup
    bindStepClicks($form);

    // On page load, re-bind steps
    $(document).on('gform_page_loaded', function (event, loadedFormId, currentPage) {
        if (loadedFormId === formId) {
            const $updatedForm = $('#gform_' + formId);
            bindStepClicks($updatedForm);

            if (wasStepClicked) {
                // Do NOT reset the input field here to preserve visited state
                wasStepClicked = false;
            }
        }
    });
}


    // Local storage functions for form data
    function storeUrlParamsToLocalStorage() {
    	const urlParams = new URLSearchParams(window.location.search);
    	const entryData = {
    		fn: urlParams.get('fn'),
    		ln: urlParams.get('ln'),
    		e: urlParams.get('e'),
    		ph: urlParams.get('ph'),
    	};

    	localStorage.setItem('babyproofersOrder', JSON.stringify(entryData));
    }

    function getBabyproofersOrderFromLocalStorage() {
    	try {
    		const data = localStorage.getItem('babyproofersOrder');
    		return data ? JSON.parse(data) : null;
    		} catch (e) {
    			console.error('Error parsing localStorage data', e);
    			return null;
    		}
    	}

    // Form submission cleanup
    function setupFormSubmissionCleanup() {
    	$(document).on('click', '#place_order', function() {
    		localStorage.removeItem('babyproofersOrder');
    	});
    }

    
    // Main initialization
    $(document).on('gform_post_render', function(event, form_id, current_page) {

    	initSelect2WithAvailable();
    	loadRoomPage();
    	setupCheckboxStates();
    	updateRoomCheckboxStates();
    	setupLoadMore();
    	setupNextButtonText();
    	bedroomSetupNextButtonText(current_page);
    	nextRoomWraperNextButtonText();
    	setTimeout(function(){
    		preselectedroompage(current_page);
    	}, 1200)



    });



    // Page-specific initialization
    if (window.location.pathname.includes('/cart')) {
    	storeUrlParamsToLocalStorage();
    }

    if (window.location.pathname.includes('/checkout')) {
    	const formEntry = getBabyproofersOrderFromLocalStorage();
    	if (formEntry) {
    		setTimeout(function() {
    			if (formEntry.fn) $("input[name='billing_first_name']").val(formEntry.fn);
    			if (formEntry.ln) $("input[name='billing_last_name']").val(formEntry.ln);
    			if (formEntry.ph) $("input[name='billing_phone']").val(formEntry.ph);
    			if (formEntry.e) $("input[name='billing_email']").val(formEntry.e);
    		}, 500);
    	}
    }

    // Initial setup
    setupPagination(4);
    setupFormSubmissionCleanup();
});