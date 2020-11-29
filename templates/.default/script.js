var LIMIT_IN_PRODUCT = 5;

$('.open-choose').on('click', function()
{
    if (isMoreQuantity())
    {
        $('.add-for-order-catalog').removeClass('loadings');
        $('.add-for-order-catalog .limit').html('<div class="success-add">Количество каждого товара, добавляемого к заказу должно быть не больше ' + LIMIT_IN_PRODUCT + '</div>');
        $('span.add-to-order-catalog').addClass('disable');
    }
    else
    {
        $('span.add-to-order-catalog').removeClass('disable');
    }
    $('#okno').css('display', 'block');
    $('.okno-back').css('display', 'block');
    $('body').prepend($('#okno'));
    $('body').prepend($('.okno-back'));
});

$('#okno .close').on('click', function()
{
    $('#okno').css('display', 'none');
    $('#content').css('position', 'relative');
    $('.okno-back').css('display', 'none');
});

$(document).on('click', '.order-list-up', function()
{
    $('.order-list').toggle();
});

$(document).on('click', '.order-list li', function()
{
    var id = $(this).attr('data-id');
    var text = $(this).text();
    $('.order-list-up').html(text);
    $('.order-list-up').attr('data-id', id);

    removeActiveForLi();
    addActiveForLi($(this));

    $('.order-list').toggle();
});

$(document).on('click', '.add-to-order-catalog', function()
{
    var id = $('.order-list-up').attr('data-id');
    if (id > 0 && !isMoreQuantity())
    {
        var dataOrder = "action=ORDER&order_id=" + id;

       sendAjax(dataOrder, id);
        $('.add-for-order-catalog').addClass('loadings');
    }
});

function removeActiveForLi()
{
    $('.order-list li.active').removeClass('active');
}

function addActiveForLi(li)
{
    li.addClass('active');
}

function sendAjax(message, id)
{
    var request = BX.ajax.runComponentAction('citrus:catalog.order', 'order', {
        mode:'class',
        data: {
            orderId: id
        }
    });

    request.then(function(response){
        console.log(response);

        if (response.data.status === 'yes')
        {

            $('.add-for-order-catalog').removeClass('loadings');
            $('.add-for-order-catalog').html(
                '<div class="success-add">Заказ успешно обновлен!</div>' +
                '<a class="go-to-order btn btn-default" href="/personal/orders/' + id + '">Перейти к заказу</a>');

        }
        else if(response.data.status === 'product_isset')
        {
            $('.add-for-order-catalog').removeClass('loadings');
            $('.add-for-order-catalog .limit').html('<div class="success-add">К заказу №' + id + ' нельзя добавлять товары из других коллекций. Уберите из корзины несоответствующие товары</div>');
        }
        else
        {
            $('.add-for-order-catalog').removeClass('loadings');
            $('.add-for-order-catalog').html('<div class="success-add">Не удалось обновить заказ!</div>');
        }
    });
}

function isMoreQuantity()
{
    var result = false;
    $("input[name*='QUANTITY_INPUT']").each(function()
    {
        if ($(this).val() > LIMIT_IN_PRODUCT)
        {
           result = true;
        }
    });

    return result;
}