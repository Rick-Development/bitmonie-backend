(function ($) {
"user strict";

//preloder
$(window).on('load', function() {
  $(".preloader").delay(1500).animate({
    "opacity": "0"
  }, 1500, function () {
      $(".preloader").css("display", "none");
  });
});

//Create Background Image
(function background() {
  let img = $('.bg_img');
  img.css('background-image', function () {
    var bg = ('url(' + $(this).data('background') + ')');
    return bg;
  });
})();

// aos
AOS.init();

// nice-select
$(".nice-select").niceSelect(),

// lightcase
 $(window).on('load', function () {
  $("a[data-rel^=lightcase]").lightcase();
})

// navbar-click
$(".navbar li a").on("click", function () {
  var element = $(this).parent("li");
  if (element.hasClass("show")) {
    element.removeClass("show");
    element.children("ul").slideUp(500);
  }
  else {
    element.siblings("li").removeClass('show');
    element.addClass("show");
    element.siblings("li").find("ul").slideUp(500);
    element.children('ul').slideDown(500);
  }
});


// scroll-to-top
$(document).ready(function () {
  "use strict";

  var progressPath = document.querySelector('.progress-wrap path');
  var pathLength = progressPath.getTotalLength();
  progressPath.style.transition = progressPath.style.WebkitTransition = 'none';
  progressPath.style.strokeDasharray = pathLength + ' ' + pathLength;
  progressPath.style.strokeDashoffset = pathLength;
  progressPath.getBoundingClientRect();
  progressPath.style.transition = progressPath.style.WebkitTransition = 'stroke-dashoffset 10ms linear';
  var updateProgress = function () {
      var scroll = $(window).scrollTop();
      var height = $(document).height() - $(window).height();
      var progress = pathLength - (scroll * pathLength / height);
      progressPath.style.strokeDashoffset = progress;
  }
  updateProgress();
  $(window).scroll(updateProgress);
  var offset = 150;
  var duration = 550;
  jQuery(window).on('scroll', function () {
      if (jQuery(this).scrollTop() > offset) {
          jQuery('.progress-wrap').addClass('active-progress');
      } else {
          jQuery('.progress-wrap').removeClass('active-progress');
      }
  });
  jQuery('.progress-wrap').on('click', function (event) {
      event.preventDefault();
      jQuery('html, body').animate({ scrollTop: 0 }, duration);
      return false;
  })
});

//Odometer
if ($(".counter").length) {
  $(".counter").each(function () {
    $(this).isInViewport(function (status) {
      if (status === "entered") {
        for (var i = 0; i < document.querySelectorAll(".odometer").length; i++) {
          var el = document.querySelectorAll('.odometer')[i];
          el.innerHTML = el.getAttribute("data-odometer-final");
        }
      }
    });
  });
}

//toggle passwoard

$(".toggle-password").click(function() {

  $(this).toggleClass("la-eye la-eye-slash");
  var input = $($(this).attr("toggle"));
  if (input.attr("type") == "password") {
  input.attr("type", "text");
  } else {
  input.attr("type", "password");
  }
  });
//toggle passwoard
$(document).ready(function() {
    $(".show_hide_password .show-pass").on('click', function(event) {
        event.preventDefault();
        if($(this).parent().find("input").attr("type") == "text"){
            $(this).parent().find("input").attr('type', 'password');
            $(this).find("i").addClass( "fa-eye-slash" );
            $(this).find("i").removeClass( "fa-eye" );
        }else if($(this).parent().find("input").attr("type") == "password"){
            $(this).parent().find("input").attr('type', 'text');
            $(this).find("i").removeClass( "fa-eye-slash" );
            $(this).find("i").addClass( "fa-eye" );
        }
    });
});

// faq
$('.faq-wrapper .faq-title').on('click', function (e) {
  var element = $(this).parent('.faq-item');
  if (element.hasClass('open')) {
    element.removeClass('open');
    element.find('.faq-content').removeClass('open');
    element.find('.faq-content').slideUp(300, "swing");
  } else {
    element.addClass('open');
    element.children('.faq-content').slideDown(300, "swing");
    element.siblings('.faq-item').children('.faq-content').slideUp(300, "swing");
    element.siblings('.faq-item').removeClass('open');
    element.siblings('.faq-item').find('.faq-title').removeClass('open');
    element.siblings('.taq-item').find('.faq-content').slideUp(300, "swing");
  }
});

// slider
var swiper = new Swiper(".virtualCard-slider", {
  slidesPerView: 1,
  spaceBetween: 30,
  speed: 1000,
  navigation: {
    nextEl: '.slider-next',
    prevEl: '.slider-prev',
  },
});

var swiper = new Swiper(".testimonial-slider", {
  slidesPerView: 1,
  spaceBetween: 30,
  loop: true,
  pagination: {
    el: ".swiper-pagination",
    clickable: true,
  },
  autoplay: {
    speed: 1000,
    delay: 3000,
  },
  speed: 1000,
  breakpoints: {
    1199: {
    slidesPerView: 1,
    },
    991: {
    slidesPerView: 1,
    },
    767: {
    slidesPerView: 1,
    },
    575: {
    slidesPerView: 1,
    },
  }
});

var swiper = new Swiper(".card-slider", {
  slidesPerView: 1,
  spaceBetween: 30,
  loop: true,
  pagination: {
    el: ".swiper-pagination",
    clickable: true,
  },
  speed: 1000,
  breakpoints: {
    1199: {
    slidesPerView: 1,
    },
    991: {
    slidesPerView: 1,
    },
    767: {
    slidesPerView: 1,
    },
    575: {
    slidesPerView: 1,
    },
  }
});


$(document).ready(function () {
  var AFFIX_TOP_LIMIT = 300;
  var AFFIX_OFFSET = 110;

  var $menu = $("#menu"),
  $btn = $("#menu-toggle");

  $("#menu-toggle").on("click", function () {
      $menu.toggleClass("open");
      return false;
  });


  $(".docs-nav").each(function () {
      var $affixNav = $(this),
    $container = $affixNav.parent(),
    affixNavfixed = false,
    originalClassName = this.className,
    current = null,
    $links = $affixNav.find("a");

      function getClosestHeader(top) {
          var last = $links.first();

          if (top < AFFIX_TOP_LIMIT) {
              return last;
          }

          for (var i = 0; i < $links.length; i++) {
              var $link = $links.eq(i),
        href = $link.attr("href");

              if (href.charAt(0) === "#" && href.length > 1) {
                  var $anchor = $(href).first();

                  if ($anchor.length > 0) {
                      var offset = $anchor.offset();

                      if (top < offset.top - AFFIX_OFFSET) {
                          return last;
                      }

                      last = $link;
                  }
              }
          }
          return last;
      }


      $(window).on("scroll", function (evt) {
          var top = window.scrollY,
        height = $affixNav.outerHeight(),
        max_bottom = $container.offset().top + $container.outerHeight(),
        bottom = top + height + AFFIX_OFFSET;

          if (affixNavfixed) {
              if (top <= AFFIX_TOP_LIMIT) {
                  $affixNav.removeClass("fixed");
                  $affixNav.css("top", 0);
                  affixNavfixed = false;
              } else if (bottom > max_bottom) {
                  $affixNav.css("top", (max_bottom - height) - top);
              } else {
                  $affixNav.css("top", AFFIX_OFFSET);
              }
          } else if (top > AFFIX_TOP_LIMIT) {
              $affixNav.addClass("fixed");
              affixNavfixed = true;
          }

          var $current = getClosestHeader(top);

          if (current !== $current) {
              $affixNav.find(".active").removeClass("active");
              $current.addClass("active");
              current = $current;
          }
      });
  });
});

// sidebar
$(".sidebar-menu-item > a").on("click", function () {
  var element = $(this).parent("li");
  if (element.hasClass("active")) {
    element.removeClass("active");
    element.children("ul").slideUp(500);
  }
  else {
    element.siblings("li").removeClass('active');
    element.addClass("active");
    element.siblings("li").find("ul").slideUp(500);
    element.children('ul').slideDown(500);
  }
});

// active menu JS
function splitSlash(data) {
  return data.split('/').pop();
}
function splitQuestion(data) {
  return data.split('?').shift().trim();
}
var pageNavLis = $('.sidebar-menu a');
var dividePath = splitSlash(window.location.href);
var divideGetData = splitQuestion(dividePath);
var currentPageUrl = divideGetData;

// find current sidevar element
$.each(pageNavLis,function(index,item){
    var anchoreTag = $(item);
    var anchoreTagHref = $(item).attr('href');
    var index = anchoreTagHref.indexOf('/');
    var getUri = "";
    if(index != -1) {
      // split with /
      getUri = splitSlash(anchoreTagHref);
      getUri = splitQuestion(getUri);
    }else {
      getUri = splitQuestion(anchoreTagHref);
    }
    if(getUri == currentPageUrl) {
      var thisElementParent = anchoreTag.parents('.sidebar-menu-item');
      (anchoreTag.hasClass('nav-link') == true) ? anchoreTag.addClass('active') : thisElementParent.addClass('active');
      (anchoreTag.parents('.sidebar-dropdown')) ? anchoreTag.parents('.sidebar-dropdown').addClass('active') : '';
      (thisElementParent.find('.sidebar-submenu')) ? thisElementParent.find('.sidebar-submenu').slideDown("slow") : '';
      return false;
    }
});

//sidebar Menu
$('.sidebar-menu-bar').on('click', function (e) {
  e.preventDefault();
  if($('.sidebar, .navbar-wrapper, .body-wrapper').hasClass('active')) {
    $('.sidebar, .navbar-wrapper, .body-wrapper').removeClass('active');
    $('.body-overlay').removeClass('active');
  }else {
    $('.sidebar, .navbar-wrapper, .body-wrapper').addClass('active');
    $('.body-overlay').addClass('active');
  }
});
$('#body-overlay').on('click', function (e) {
  e.preventDefault();
  $('.sidebar, .navbar-wrapper, .body-wrapper').removeClass('active');
  $('.body-overlay').removeClass('active');
});

// dashboard-list
$('.dashboard-list-item').on('click', function (e) {
  if(e.target.classList.contains("delate-btn")|| e.target.closest(".delate-btn")) {
    return false;
  }
  if(e.target.classList.contains("select-btn")) {
    return false;
  }

  var element = $(this).parent('.dashboard-list-item-wrapper');
  if (element.hasClass('show')) {
    element.removeClass('show');
    element.find('.preview-list-wrapper').removeClass('show');
    element.find('.preview-list-wrapper').slideUp(300, "swing");
  } else {
    element.addClass('show');
    element.children('.preview-list-wrapper').slideDown(300, "swing");
    element.siblings('.dashboard-list-item-wrapper').children('.preview-list-wrapper').slideUp(300, "swing");
    element.siblings('.dashboard-list-item-wrapper').removeClass('show');
    element.siblings('.dashboard-list-item-wrapper').find('.dashboard-list-item').removeClass('show');
    element.siblings('.dashboard-list-item-wrapper').find('.preview-list-wrapper').slideUp(300, "swing");
  }
});

// invoice-form
$('.invoice-form').on('click', '.add-row-btn', function() {
  $('.add-row-btn').closest('.invoice-form').find('.add-row-wrapper').last().clone().show().appendTo('.results');
});

$(document).on('click','.invoice-cross-btn', function (e) {
e.preventDefault();
$(this).parent().parent().hide(300);
});

//pdf
$('.pdf').on('click', function (e) {
  e.preventDefault();
  $('.pdf-area').addClass('active');
  $('.body-overlay').addClass('active');
});
$('#body-overlay, #pdf-area').on('click', function (e) {
  e.preventDefault();
  $('.pdf-area').removeClass('active');
  $('.body-overlay').removeClass('active');
})

//Notification
$('.notification-icon').on('click', function (e) {
  e.preventDefault();
  if($('.notification-wrapper').hasClass('active')) {
    $('.notification-wrapper').removeClass('active');
    $('.body-overlay').removeClass('show');
  }else {
    $('.notification-wrapper').addClass('active');
    $('.body-overlay').addClass('show');
  }
});
$('#body-overlay').on('click', function (e) {
  e.preventDefault();
  $('.notification-wrapper').removeClass('active');
  $('.body-overlay').removeClass('show');
});


//Profile Upload
function proPicURL(input) {
  if (input.files && input.files[0]) {
      var reader = new FileReader();
      reader.onload = function (e) {
          var preview = $(input).parents('.preview-thumb').find('.profilePicPreview');
          $(preview).css('background-image', 'url(' + e.target.result + ')');
          $(preview).addClass('has-image');
          $(preview).hide();
          $(preview).fadeIn(650);
      }
      reader.readAsDataURL(input.files[0]);
  }
}
$(".profilePicUpload").on('change', function () {
  proPicURL(this);
});

$(".remove-image").on('click', function () {
  $(".profilePicPreview").css('background-image', 'none');
  $(".profilePicPreview").removeClass('has-image');
});


//info-btn
$(document).on('click', '.info-btn', function () {
  $('.support-profile-wrapper').addClass('active');
});
$(document).on('click', '.chat-cross-btn', function () {
  $('.support-profile-wrapper').removeClass('active');
});


$(document).on("click",".card-custom",function(){
  $(this).toggleClass("active");
});

//acoount-toggle
$('.header-account-btn').on('click', function (e) {
  e.preventDefault();
  $('.account-section').addClass('active');
  $('.body-overlay').addClass('active');
});
$('#body-overlay').on('click', function (e) {
  e.preventDefault();
  $('.account-section').removeClass('active');
  $('.body-overlay').removeClass('active');
});
$('.account-close').on('click', function (e) {
  e.preventDefault();
  $('.account-section').removeClass('active');
  $('.body-overlay').removeClass('active');
});
$('.remove-account').on('click', function (e) {
  e.preventDefault();
  $(this).parent().parent().hide(300);
});
$('.account-control-btn').on('click', function () {
  $('.account-area').toggleClass('change-form');
})


$(".account-control-btn").click(function(){
  var source = $(this).attr("data-block");
  $(".account-wrapper").hide();
  $(".account-wrapper."+source).show();
});



// select-2 init
$('.select2-basic').select2();
$('.select2-multi-select').select2();
$(".select2-auto-tokenize").select2({
tags: true,
tokenSeparators: [',']
});


$("form button[type=submit], form input[type=submit]").on("click", function (event) {
    var inputFileds = $(this).parents("form").find("input[type=text], input[type=number], input[type=email], input[type=password]");
    var mode = false;
    $.each(inputFileds, function (index, item) {
        if ($(item).attr("required") != undefined) {
            if ($(item).val() == "") {
                mode = true;
            }
        }
    });
    if (mode == false) {
        $(this).parents("form").find(".btn-ring").show();
        $(this).parents("form").find("button[type=submit],input[type=submit]").prop("disabled", true);
        $(this).parents("form").submit();
    }
});

$(document).ready(function () {
    $.each($(".btn-loading"), function (index, item) {
        $(item).append(`<span class="btn-ring"></span>`);
    });
});

// switch-toggles
$(document).ready(function(){
  $.each($(".switch-toggles"),function(index,item) {
    var firstSwitch = $(item).find(".switch").first();
    var lastSwitch = $(item).find(".switch").last();
    if(firstSwitch.attr('data-value') == null) {
      $(item).find(".switch").first().attr("data-value",true);
      $(item).find(".switch").last().attr("data-value",false);
    }
    if($(item).hasClass("active")) {
      $(item).find('input').val(firstSwitch.attr("data-value"));
    }else {
      $(item).find('input').val(lastSwitch.attr("data-value"));
    }
  });
});

$('.switch-toggles .switch').on('click', function () {
  $(this).parents(".switch-toggles").toggleClass('active');
  $(this).parents(".switch-toggles").find("input").val($(this).attr("data-value"));
  
  let targetAttrVal = $(this).parent().attr("data-deactive");
  if($(this).parent().hasClass("active") == false) {
    $('[data-switcher='+targetAttrVal+']').removeClass("d-none").slideDown(400);
  }else {
    $('[data-switcher='+targetAttrVal+']').slideUp(400);
  }
});

})(jQuery);


/************* Custom Js  ***************/

function openAlertModal(URL,target,message,actionBtnText = "Remove",method = "DELETE"){
    if(URL == "" || target == "") {
        return false;
    }

    if(message == "") {
        message = "Are you sure to delete ?";
    }

    var method = `<input type="hidden" name="_method" value="${method}">`;
    openModalByContent(
        {
            content: `<div class="card modal-alert border-0">
                        <div class="card-body">
                            <form method="POST" action="${URL}">
                                <input type="hidden" name="_token" value="${laravelCsrf()}">
                                ${method}
                                <div class="head mb-3" style="color: #000000">
                                    ${message}
                                    <input type="hidden" name="target" value="${target}">
                                    <input type="hidden" name="type" value="${actionBtnText}">
                                </div>
                                <div class="foot d-flex align-items-center justify-content-between">
                                    <button type="button" class="modal-close btn btn--info rounded text-light">Close</button>
                                    <button type="submit" class="alert-submit-btn btn btn--danger btn-loading rounded text-light">${actionBtnText}</button>
                                </div>
                            </form>
                        </div>
                    </div>`,
        },

    );
}

function openModalByContent(data = {
content:"",
animation: "mfp-move-horizontal",
size: "medium",
}) {
$.magnificPopup.open({
    removalDelay: 500,
    items: {
    src: `<div class="white-popup mfp-with-anim ${data.size ?? "medium"}">${data.content}</div>`, // can be a HTML string, jQuery object, or CSS selector
    },
    callbacks: {
    beforeOpen: function() {
        this.st.mainClass = data.animation ?? "mfp-move-horizontal";
    },
    open: function() {
        var modalCloseBtn = this.contentContainer.find(".modal-close");
        $(modalCloseBtn).click(function() {
        $.magnificPopup.close();
        });
    },
    },
    midClick: true,
});
}

/**
 * Function For Get All Country list by AJAX Request
 * @param {HTML DOM} targetElement
 * @param {Error Place Element} errorElement
 * @returns
 */
var allCountries = "";
function getAllCountries(hitUrl,targetElement = $(".country-select"),errorElement = $(".country-select").siblings(".select2")) {
  if(targetElement.length == 0) {
    return false;
  }
  var CSRF = $("meta[name=csrf-token]").attr("content");
  var data = {
    _token      : CSRF,
  };
  $.post(hitUrl,data,function() {
    // success
    $(errorElement).removeClass("is-invalid");
    $(targetElement).siblings(".invalid-feedback").remove();
  }).done(function(response){
    // Place States to States Field
    var options = "<option selected disabled>Select Country</option>";
    var selected_old_data = "";
    if($(targetElement).attr("data-old") != null) {
        selected_old_data = $(targetElement).attr("data-old");
    }
    $.each(response,function(index,item) {
        options += `<option value="${item.name}" data-id="${item.id}" data-mobile-code="${item.mobile_code}" ${selected_old_data == item.name ? "selected" : ""}>${item.name}</option>`;
    });


    allCountries = response;

    $(targetElement).html(options);
  }).fail(function(response) {
    var faildMessage = "Something went wrong! Please try again.";
    var faildElement = `<span class="invalid-feedback" role="alert">
                            <strong>${faildMessage}</strong>
                        </span>`;
    $(errorElement).addClass("is-invalid");
    if($(targetElement).siblings(".invalid-feedback").length != 0) {
        $(targetElement).siblings(".invalid-feedback").text(faildMessage);
    }else {
      errorElement.after(faildElement);
    }
  });
}
// getAllCountries();


/**
 * Function for reload the all countries that already loaded by using getAllCountries() function.
 * @param {string} targetElement
 * @param {string} errorElement
 * @returns
 */
function reloadAllCountries(targetElement,errorElement = $(".country-select").siblings(".select2")) {
  if(allCountries == "" || allCountries == null) {
  // alert();
  return false;
  }
  var options = "<option selected disabled>Select Country</option>";
  var selected_old_data = "";
  if($(targetElement).attr("data-old") != null) {
    selected_old_data = $(targetElement).attr("data-old");
  }
  $.each(allCountries,function(index,item) {
    options += `<option value="${item.name}" data-id="${item.id}" data-currency-name="${item.currency_name}" data-currency-code="${item.currency_code}" data-currency-symbol="${item.currency_symbol}" ${selected_old_data == item.name ? "selected" : ""}>${item.name}</option>`;
  });
  $(targetElement).html(options);
}

function placePhoneCode(code) {
    if(code != undefined) {
        code = code.replace("+","");
        code = "+" + code;
        $("input.phone-code").val(code);
        $("div.phone-code").html(code);
    }
}

function formAjaxRequest(formData,URL) {
    var data = formData;
    $.post(URL,data,function(response) {
      //response
    }).done(function(response){
      if(response.data.url != undefined) {
        return window.location.href = response.data.url;
      }else {
        throwMessage('error',['Something went wrong! Please try again']);
      }
      // console.log(response);
    }).fail(function(response) {
        $('.btn-loading .btn-ring').hide();
        $("form").find("button[type=submit],input[type=submit]").prop("disabled", false);
        $('#verification-modal').modal('hide');
        var response = JSON.parse(response.responseText);
        throwMessage(response.type,response.message.error);
        if(response.data.url != undefined) {
            setTimeout(() => {
            return window.location.href = response.data.url;
            }, 1000);
        }
    });
  }

  var timeOut;
  function itemSearch(inputElement,tableElement,URL,minTextLength = 3) {
    $(inputElement).bind("keyup",function(){
      clearTimeout(timeOut);
      timeOut = setTimeout(executeItemSearch, 500,$(this),tableElement,URL,minTextLength);
    });
  }

  function executeItemSearch(inputElement,tableElement,URL,minTextLength) {
    $(tableElement).parent().find(".search-result-table").remove();
    var searchText = inputElement.val();
    if(searchText.length > minTextLength) {
      // console.log(searchText);
      $(tableElement).addClass("d-none");
      makeSearchItemXmlRequest(searchText,tableElement,URL);
    }else {
      $(tableElement).removeClass("d-none");
    }
  }

  function makeSearchItemXmlRequest(searchText,tableElement,URL) {
    var data = {
      _token      : laravelCsrf(),
      text        : searchText,
    };
    $.post(URL,data,function(response) {
      //response
    }).done(function(response){
      itemSearchResult(response,tableElement);
    }).fail(function(response) {
      throwMessage('error',["Something went wrong! Please try again."]);
    });
  }

  function itemSearchResult(response,tableElement) {
    if(response == "") {
      throwMessage('error',["No data found!"]);
    }
    if($(tableElement).siblings(".search-result-table").length > 0) {
      $(tableElement).parent().find(".search-result-table").html(response);
    }else{
      $(tableElement).after(`<div class="search-result-table"></div>`);
      $(tableElement).parent().find(".search-result-table").html(response);
    }
  }

  function postFormAndSubmit(action,target) {
    var postForm = `<form id="post-form-dy" action="${action}" method="POST">
      <input type="hidden" name="_token" value="${laravelCsrf()}" />
      <input type="hidden" name="target" value="${target}" />
    </form>`;
    $("body").append(postForm);
    $("#post-form-dy").submit();
  }


  $(document).ready(function(){
    var forms = $(".onload-from");
    $.each(forms,function(index,item) {
      $(item).submit(function(event){
        event.preventDefault();
        var formData = $(item).serialize();
        var submitURL = $(item).attr("action");
        formAjaxRequest(formData,submitURL);
      });
    });
  });


  /**
 * Function for open delete modal with method DELETE
 * @param {string} URL
 * @param {string} target
 * @param {string} message
 * @returns
 */
function openDeleteModal(URL,target,message,actionBtnText = "Remove",method = "DELETE"){
    if(URL == "" || target == "") {
        return false;
    }

    if(message == "") {
        message = "Are you sure to delete ?";
    }
    var method = `<input type="hidden" name="_method" value="${method}">`;
    openModalByContent(
        {
            content: `<div class="card modal-alert border-0">
                        <div class="card-body">
                            <form method="POST" action="${URL}">
                                <input type="hidden" name="_token" value="${laravelCsrf()}">
                                ${method}
                                <div class="head mb-3">
                                    ${message}
                                    <input type="hidden" name="target" value="${target}">
                                </div>
                                <div class="foot d-flex align-items-center justify-content-between">
                                    <button type="button" class="modal-close btn btn--info text-white">Close</button>
                                    <button type="submit" class="alert-submit-btn btn btn--danger text-white btn-loading">${actionBtnText}</button>
                                </div>
                            </form>
                        </div>
                    </div>`,
        },

    );
}


function copyToClipBoard(copyId) {
    var copyText = document.getElementById(copyId);
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);
    notification('success', 'URL Copied To Clipboard!');
}


function removeTrailingZeros(str) {
    // return str.replace(/^0+(\d)|(\d)0+$/gm, '$1$2');
    return str;
}


// ********* Verification Code Send ************** //
function sendVerificationCode(url, method, resendCodeLink) {
    $.ajax({
        type: method,
        url:url,
        dataType: "json",
        beforeSend: function(){
            $('.btn-loading .btn-ring').show();
        },
        complete: function(){
            $('.btn-loading .btn-ring').hide();
        },
        success: function (data) {
            $('#verification-modal .to-address').text(data.data.to_address);
            $('#verification-modal .exist').text('');
            $('#verification-modal .password_check').val('');
            $('#verification-modal .resend_time').val(data.data.resend_time);

            second = data.data.resend_time;
            var coundDownSec = second;
            var countDownDate = new Date();
            countDownDate.setMinutes(countDownDate.getMinutes() + 120);
            var x = setInterval(function () {  // Get today's date and time
                var now = new Date().getTime();  // Find the distance between now and the count down date
                var distance = countDownDate - now;  // Time calculations for days, hours, minutes and seconds  var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((distance % (1000 * coundDownSec)) / (1000 * coundDownSec));
                var seconds = Math.floor((distance % (1000 * coundDownSec)) / 1000);  // Output the result in an element with id="time"
                document.getElementById("time").innerHTML =second + "s ";  // If the count down is over, write some text
                if (distance <= 0 || second <= 0 ) {
                    clearInterval(x);
                    // document.getElementById("time").innerHTML = "RESEND";
                    document.querySelector(".time-area").innerHTML = `Didn't get the code? <a class='text--danger' href='${resendCodeLink}'>Resend</a>`;
                }
                second--
            }, 1000);

            $('#verification-modal').modal('show');
        },
        error: function(xhr, ajaxOption, thrownError){
            var errorObj = JSON.parse(xhr.responseText);
            throwMessage(errorObj.type,errorObj.message.error.errors);
        },
    });
}

function verificationCodeCheck(method, url, data){
    $.ajax({
        type    : method,
        url     : url,
        data    : data,
        dataType: "json",
        success: function (data) {
            if(data.data.check == true){
                if($('#verification-modal .exist').hasClass('text--danger')){
                    $('#verification-modal .exist').removeClass('text--danger');
                }
                $('#verification-modal .exist').text(`Verification Code Matched Successfully.`).addClass('text--success');
                $('#verification-modal .final_confirm_btn').show();
                $('#verification-modal .final_confirm_btn').attr('disabled',false)
            }else{
                if($('#verification-modal .exist').hasClass('text--success')){
                    $('#verification-modal .exist').removeClass('text--success');
                }
                $('#verification-modal .exist').text('Your Entered Code Doesn\'t Matched.').addClass('text--danger');
                $('#verification-modal .final_confirm_btn').attr('disabled',true)
                $('#verification-modal .final_confirm_btn').hide();
            }
        },
        error: function(xhr, ajaxOption, thrownError){
            if($('#verification-modal .exist').hasClass('text--success')){
                $('#verification-modal .exist').removeClass('text--success');
            }
            $('#verification-modal .exist').text('Your Entered Code Doesn\'t Matched.').addClass('text--danger');
            $('#verification-modal .final_confirm_btn').attr('disabled',true)
            $('#verification-modal .final_confirm_btn').hide();
            throwMessage('error', errorObj.message.error.errors ?? errorObj.message.error.error);
        },
    });
}

$(document).on("keyup",".number-input",function(){
  var pattern = /^[0-9]*\.?[0-9]*$/;
  var value = $(this).val();
  var test = pattern.test(value);
  if(test == false) {
    var rightValue = value;
    if(value.length > 0) {
      for (let index = 0; index < value.length; index++){
        if(!$.isNumeric(rightValue)) {
          rightValue = rightValue.slice(0, -1);
        }
      }
    }
    $(this).val(rightValue);
  }
});

/**
 * Function for make ajax request for switcher
 * @param {HTML DOM} inputName
 * @param {AJAX URL} hitUrl
 * @param {URL METHOD} method
 */
function switcherAjax(hitUrl,method = "PUT") {
  $(document).on("click",".event-ready",function(event) {
    var inputName = $(this).parents(".switch-toggles").find("input").attr("name");
    if(inputName == undefined || inputName == "") {
      return false;
    }
    $(this).parents(".switch-toggles").find(".switch").removeClass("event-ready");
    var input = $(this).parents(".switch-toggles").find("input[name="+inputName+"]");
    
    var eventElement = $(this);
    if(input.length == 0) {
        alert("Input field not found.");
        $(this).parents(".switch-toggles").find(".switch").addClass("event-ready");
        $(this).find(".btn-ring").hide();
        return false;
    }
    var CSRF = $("head meta[name=csrf-token]").attr("content");
    var dataTarget = "";
    if(input.attr("data-target")) {
        dataTarget = input.attr("data-target");
    }
    var inputValue = input.val();
    var data = {
      _token: CSRF,
      _method: method,
      data_target: dataTarget,
      status: inputValue,
      input_name: inputName,
    };
    $.post(hitUrl,data,function(response) {
      throwMessage('success',response.message.success);
      // Remove Loading animation
      
      $(event.target).find(".btn-ring").hide();
    }).done(function(response){
      
      $(eventElement).parents(".switch-toggles").find(".switch").addClass("event-ready");
      // $(eventElement).parents(".switch-toggles.btn-load").toggleClass('active');
      var dataValue = $(eventElement).parents(".switch-toggles").find(".switch").last().attr("data-value");
      if($(eventElement).parents(".switch-toggles").hasClass("active")) {
        dataValue = $(eventElement).parents(".switch-toggles").find(".switch").first().attr("data-value");
        // $(eventElement).parents(".switch-toggles").find(".switch").first().find(".btn-ring").hide();
      }
      $(eventElement).parents(".switch-toggles.btn-load").find("input").val(dataValue);
      // $(eventElement).parents(".switch-toggles").find(".switch").find(".btn-ring").hide();
      
    }).fail(function(response) {
        var response = JSON.parse(response.responseText);
        throwMessage(response.type,response.message.error);
        $(eventElement).parents(".switch-toggles").find(".switch").addClass("event-ready");
        $(eventElement).parents(".switch-toggles").find(".btn-ring").hide();
        
        return false;
    });
  });
}