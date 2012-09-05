/*
 * shopp.js - Shopp behavioral utility library
 * Copyright ?? 2008-2010 by Ingenesis Limited
 * Licensed under the GPLv3 {@see license.txt}
 */
function jqnc(){return jQuery.noConflict()}function copyOf(c){var b=new Object(),a;for(a in c){b[a]=c[a]}return b}if(!Array.indexOf){Array.prototype.indexOf=function(b){for(var a=0;a<this.length;a++){if(this[a]==b){return a}}return -1}}function getCurrencyFormat(a){if(a&&a.currency){return a}if($s&&$s.d!==""&&$s.d!==undefined){return{cpos:$s.cp,currency:$s.c,precision:parseInt($s.p,10),decimals:$s.d,thousands:$s.t,grouping:$s.g}}return{cpos:true,currency:"$",precision:2,decimals:".",thousands:",",grouping:[3]}}function asMoney(b,a){a=getCurrencyFormat(a);b=formatNumber(b,a);if(a.cpos){return a.currency+b}return b+a.currency}function asPercent(d,a,b,c){a=getCurrencyFormat(a);a.precision=b?b:1;return formatNumber(d,a,c).replace(/0+$/,"").replace(new RegExp("\\"+a.decimals+"$"),"")+"%"}function formatNumber(e,l,a){l=getCurrencyFormat(l);e=asNumber(e);var c,k,o=fraction=0,j=false,h="",g=[],m=e.toFixed(l.precision).toString().split("."),b=l.grouping?l.grouping:[3];e="";o=m[0];if(m[1]){fraction=m[1]}if(b.indexOf(",")>-1){b=b.split(",")}else{b=[b]}k=0;lg=b.length-1;while(o.length>b[Math.min(k,lg)]){if(b[Math.min(k,lg)]==""){break}j=o.length-b[Math.min(k++,lg)];h=o;o=h.substr(0,j);g.unshift(h.substr(j))}if(o){g.unshift(o)}e=g.join(l.thousands);if(e==""){e=0}fraction=(a)?new Number("0."+fraction).toString().substr(2,l.precision):fraction;fraction=(!a||a&&fraction.length>0)?l.decimals+fraction:"";if(l.precision>0){e+=fraction}return e}function asNumber(b,a){if(!b){return 0}a=getCurrencyFormat(a);if(b instanceof Number){return new Number(b.toFixed(a.precision))}b=b.toString().replace(a.currency,"");b=b.toString().replace(new RegExp(/(\D\.|[^\d\,\.\-])/g),"");b=b.toString().replace(new RegExp("\\"+a.thousands,"g"),"");if(a.precision>0){b=b.toString().replace(new RegExp("\\"+a.decimals,"g"),".")}if(isNaN(new Number(b))){b=b.replace(new RegExp(/\./g),"").replace(new RegExp(/\,/),".")}return new Number(b)}function CallbackRegistry(){this.callbacks=new Array();this.register=function(a,b){this.callbacks[a]=b};this.call=function(d,c,b,a){this.callbacks[d](c,b,a)};this.get=function(a){return this.callbacks[a]}}if(!Number.prototype.roundFixed){Number.prototype.roundFixed=function(a){var b=Math.pow(10,a||0);return String(Math.round(this*b)/b)}}function quickSelects(b){var a=jQuery(b).find("input.selectall");if(a.size()==0){a=jQuery("input.selectall")}a.unbind("mouseup.select").bind("mouseup.select",function(){this.select()})}function htmlentities(a){if(!a){return""}a=a.replace(new RegExp(/&#(\d+);/g),function(){return String.fromCharCode(RegExp.$1)});return a}function debuglog(a){if(window.console!=undefined){console.log(a)}}jQuery.fn.fadeRemove=function(b,c){var a=jQuery(this);a.fadeOut(b,function(){a.remove();if(c){c()}});return this};jQuery.fn.hoverClass=function(){var a=jQuery(this);a.hover(function(){a.addClass("hover")},function(){a.removeClass("hover")});return this};jQuery.fn.clickSubmit=function(){var a=jQuery(this);a.click(function(){jQuery(this).closest("form").submit()});return this};jQuery.fn.setDisabled=function(a){var b=jQuery(this);b.each(function(){if(a){b.attr("disabled",true).addClass("disabled")}else{b.attr("disabled",false).removeClass("disabled")}});return this};jQuery.parseJSON=function(data){if(typeof(JSON)!=="undefined"&&typeof(JSON.parse)==="function"){try{return JSON.parse(data)}catch(e){return false}}else{return eval("("+data+")")}};jQuery.getQueryVar=function(b,a){b=b.replace(/[\[]/,"\\[").replace(/[\]]/,"\\]");var d=new RegExp("[\\?&]"+b+"=([^&#]*)"),c=d.exec(a);if(c==null){return""}else{return decodeURIComponent(c[1].replace(/\+/g," "))}};jQuery(document).ready(function(a){a("input.currency, input.money").change(function(){this.value=asMoney(this.value)}).change();a(".click-submit").clickSubmit();quickSelects()});