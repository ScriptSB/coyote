!function(t){"use strict";t.fn.wikiEditor=function(){return this.each(function(){var e=t(this),n=t("#wiki-toolbar");t(".btn-group button",n).click(function(){e.insertAtCaret(t(this).data("open"),t(this).data("close")," ")}),t(e).bind("keydown",function(t){return 9!==t.which&&9!==t.keyCode||!t.shiftKey?void 0:(e.insertAtCaret("	","",""),!1)})})},t.fn.extend({insertAtCaret:function(t,e,n){var i=this[0];if(document.selection){i.focus();var o=document.selection.createRange();o.text=t+(o.text.length>0?o.text:n)+e,i.focus()}else if(i.selectionStart||"0"==i.selectionStart){var s=i.selectionStart,c=i.selectionEnd,r=i.scrollTop;n=s!==c?t+i.value.substring(s,c)+e:t+n+e,i.value=i.value.substring(0,s)+n+i.value.substring(c,i.value.length),i.focus(),i.selectionStart=s+n.length,i.selectionEnd=s+n.length,i.scrollTop=r}else i.value+=t+n+e,i.focus()}})}(jQuery);
//# sourceMappingURL=wikieditor.js.map