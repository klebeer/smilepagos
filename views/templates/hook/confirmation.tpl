<section id="order-summary-content" class="page-content page-order-confirmation">
    {if $status == 'ok'}
        <img src="{$this_path|escape:'html':'UTF-8'}/views/img/smileDatafast.png" width="230" height="60"/>
        <div class="box order-confirmation">
            <p class="alert alert-success">{l s='Su pedido en %s está completo.' sprintf=[$shop_name] mod='smilepagos'}</p>


            <table>
                <tbody>
                <tr>
                    <td>{l s='Tarjeta' mod='smilepagos'}&nbsp;</td>
                    <td>&nbsp;{$dataFastBrand}</td>
                </tr>
                <tr>
                    <td>{l s='Nombre' mod='smilepagos'}&nbsp;</td>
                    <td>&nbsp;{$dataFastCardHolder}</td>
                </tr>
                <tr>
                    <td>{l s='Monto' mod='smilepagos'}&nbsp;</td>
                    <td>&nbsp;{$dataFastAmount}</td>
                </tr>
                <tr>
                    <td>{l s='Autorización' mod='smilepagos'}&nbsp;</td>
                    <td>&nbsp;{$dataFastAuth}</td>
                </tr>

                </tbody>
            </table>

            <br/>
            <p><strong>{l s='Su pedido será enviado lo antes posible.' mod='smilepagos'}</strong></p>
            <p>
                {l s='Para cualquier consulta o para más información, contacte con nuestro' mod='smilepagos'}
                <a href="{$link->getPageLink('contact', true)|escape:'html':'UTF-8'}">{l s='atención al cliente' mod='smilepagos'}</a>.
            </p>
        </div>
    {else}
        <p class="alert alert-warning">{l s='Se produjo un error al procesar el pago.' mod='smilepagos'}</p>
        <div class="box order-confirmation">
            {if !empty($message)}
                <p>{$message|escape:'html':'UTF-8'}</p>
            {/if}
            <p>
                {l s='Para cualquier consulta o para más información, contacte con nuestro' mod='smilepagos'}
                <a href="{$link->getPageLink('contact', true)|escape:'html':'UTF-8'}">{l s='atención al cliente' mod='smilepagos'}</a>.
            </p>
        </div>
    {/if}
</section>