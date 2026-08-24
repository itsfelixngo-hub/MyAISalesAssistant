var btn_menu = document.getElementById('btn-menu');
var mobile = document.getElementById('mobile');
var menus_mobile = document.getElementById('menus-mobile');

if(btn_menu){
  btn_menu.addEventListener('click', function() {
    mobile.classList.toggle('opened');
  }, false);
}
$(document).on("click", "label.search__label-init", function(){
	$("body").addClass("search-box");
});
$(document).on("click", "label.search__label-active", function(){
	$("body").removeClass("search-box");
});
$(document).on("click",".showmore_trigger", function(){
	$(this).addClass("active");
	$(this).html("<span>Show less</span>");
	$("#describe").css('height', '100%');
});
$(document).on("click",".showmore_trigger.active", function(){
	$(this).removeClass("active");
	$(this).html("<span>Show more</span>");
	$("#describe").css('height', '200px');
});
// $(document).on("click", "li.menu-item-has-children .plus", function(){
//     $(this).toggleClass("sub");
//     $(this).next().toggleClass("opened");
// });
$(document).ready(function() {
$(".showmore_trigger").append("<span>Show more</span>");
// $(".menus-mobile ul li.menu-item-has-children > a").after("<div id='li-plus' class='plus'></div>");
// $(".nav-lang").hover(
//   function() {
//     $( "ul.ic-lang").css("display","block");
//   },function() {
//     $( "ul.ic-lang").css("display","none");
//   });
});