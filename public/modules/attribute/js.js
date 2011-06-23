$(function () {
	$('.ulAtr').hide();
	$('.ulOpt').hide();

	// Expand Atrg
	$('.ancExpandAtrg').click(function () {
		id = $(this).attr('id').match(/ancExpandAtrg\.(\d+)/)[1];
		send = { 'cs': 'attribute',
			'ca': 'getatrg',
			'ci': id };
		$('#ulAtrg\\.'+id).load('index.php', send);
		return false;
	});

	// Create Atrg
	$('.ancCreateAtrg').click(function() {
		hideEdit();
		type = 'Atrg';
		$(this).parent().before('<li id="liEdit">'+
			'Name <input type="text" id="txtCreateAtrg" />'+
			'<input type="button" value="Create" class="butSave" />'+
			'<input type="button" value="Cancel" class="butCancel" /></li>');
		$('#liEdit').hide();
		$('#liEdit').show(500, function () { $(this).css('display', ''); });
		linkCreate();
		return false;
	});

	// Edit Atrg
	$('.ancEditAtrg').click(function () {
		hideEdit();
		type = 'Atrg';
		id = $(this).attr('id').match(/ancEditAtrg\.(\d+)/)[1];
		targets = {'atrg_name': $('#ancExpandAtrg\\.'+id)};
		//editing = [target];
		targets['atrg_name'].parent().before('<li id="liEdit">'+
			'Name <input type="text" class="edit" name="atrg_name" value="'+targets['atrg_name'].text()+'" />'+
			'<input type="button" value="Save" class="butSave" />'+
			'<input type="button" value="Cancel" class="butCancel" /></li>');
		$('#liEdit').hide();
		$('#liEdit').show(500, function () { $(this).css('display', ''); });
		linkEdit();
		return false;
	});

	// Delete Atrg
	$('.ancDeleteAtrg').click(function () {
		hideEdit();
		id = $(this).attr('id').match(/ancDeleteAtrg\.(\d+)/)[1];
		if (confirm('Are you sure?')) sendDelete('Atrg', id);
		return false;
	});

	// Expand Atr
	$('.ancExpandAtr').click(function () {
		target = $(this).attr('id').match(/ancExpandAtr\.(\d+)/)[1];
		$('#ulAtr\\.'+target).toggle(500);
		return false;
	});

	// Create Atr
	$('.ancCreateAtr').click(function() {
		hideEdit();
		type = 'Atr';
		parent = $(this).parent().parent().attr('id').match(/ulAtrg\.(\d+)/)[1];
		$(this).parent().before('<li id="liEdit">'+
			'Name <input type="text" name="atr_name" class="edit" /><br/>'+
			'Type <select class="edit" name="atr_type">'+
			'<option value="0">Select</option>'+
			'<option value="1">Numeric</option>'+
			'</select>'+
			'<input type="button" value="Create" class="butSave" />'+
			'<input type="button" value="Cancel" class="butCancel" /></li>');
		$('#liEdit').hide();
		$('#liEdit').show(500, function () { $(this).css('display', ''); });
		linkCreate();
		return false;
	});

	// Edit Atr
	$('.ancEditAtr').click(function () {
		hideEdit();
		type = 'Atr';
		id = $(this).attr('id').match(/ancEditAtr\.(\d+)/)[1];
		targets = {'atr_name': $('#ancExpandAtr\\.'+id)};
		targets['atr_name'].parent().before('<li id="liEdit">'+
			'Name <input type="text" class="edit" name="atr_name" value="'+
			targets['atr_name'].text()+'" />'+
			'<input type="button" value="Save" class="butSave" />'+
			'<input type="button" value="Cancel" class="butCancel" /></li>');
		$('#liEdit').hide();
		$('#liEdit').show(500, function () { $(this).css('display', ''); });
		linkEdit();
		return false;
	});

	// Delete Atr
	$('.ancDeleteAtr').click(function () {
		hideEdit();
		id = $(this).attr('id').match(/ancDeleteAtr\.(\d+)/)[1];
		if (confirm('Are you sure?')) sendDelete('Atr', id);
		return false;
	});

	// Create Option
	$('.ancCreateOpt').click(function () {
		type = 'Opt';
		parent = $(this).parent().parent().attr('id').match(/ulAtr\.(\d+)/)[1];
		id = $(this).attr('id').match(/ancCreateOpt\.(\d+)/)[1];

		targets = {
			'opt_name': $('#spnOpt\\.'+id),
			'opt_formula': $('#spnFor\\.'+id)
		};

		hideEdit();
		$(this).parent().before('<li id="liEdit">'+
			'<table>'+
			'<tr><td>Name</td><td><input type="text" class="edit" name="opt_name" id="opt_name" /></td></tr>'+
			'<tr><td>Formula</td><td><input type="text" class="edit" name="opt_formula" id="opt_formula" /></td></tr>'+
			'</table>'+
			'<input type="button" value="Create" class="butSave" />'+
			'<input type="button" value="Cancel" class="butCancel" /></li>');
		$('#liEdit').hide();
		$('#liEdit').show(500, function () { $(this).css('display', ''); });
		linkCreate();
		return false;
	});

	// Edit Option
	$('.ancEditOpt').click(function () {
		hideEdit();
		type = 'Opt';
		id = $(this).attr('id').match(/ancEditOpt\.(\d+)/)[1]

		targets = {
			'opt_name': $('#spnOpt\\.'+id),
			'opt_formula': $('#spnFor\\.'+id)
		};

		targets['opt_formula'].parent().before('<li id="liEdit">'+
			'<table>'+
			'<tr><td>Name</td><td><input type="text" class="edit"'+
				' name="opt_name" id="txtName" value="'+
				targets['opt_name'].text()+
				'" /></td></tr>'+
			'<tr><td>Formula</td><td><input type="text" class="edit"'+
				' name="opt_formula" id="txtFormula" value="'+
				targets['opt_formula'].text()+
				'" /></td></tr>'+
			'</table>'+
			'<input type="button" value="Save" class="butSave" />'+
			'<input type="button" value="Cancel" class="butCancel" /></li>');
		$('#liEdit').hide();
		$('#liEdit').show(500, function () { $(this).css('display', ''); });
		linkEdit();
		return false;
	});

	//Delete Option
	$('.ancDeleteOpt').click(function () {
		hideEdit();
		id = $(this).attr('id').match(/ancDeleteOpt\.(\d+)/)[1];
		if (confirm('Are you sure?')) sendDelete('Opt', id);
		return false;
	});

	// When updating a cart item attribute
	$('.cart-item').find('.product_value').live('change', function () {
		form = $(this).closest('.form');
		id = form.attr('id').match(/frmCart_(\d+)/)[1];
		$.post(app_abs+'/cart/update/'+id, form.serialize(), function () {
			$('#divCart').load(app_abs+'/cart/part')
		});
	});
});

function hideEdit()
{
	$('#liEdit').hide(500, function () { $(this).remove() });
}

function linkCreate()
{
	$('.butSave').click(function () {
		target = $('#txtCreate'+type);
		val = target.val()

		vars = {
			'cs': 'attribute',
			'ca': 'create',
			'type': type,
			'parent': parent
		};

		$('.edit').each (function () {
			vars[$(this).attr('name')] = $(this).val();
		});

		$.post('index.php', vars, function (res) {
			if (res.res == 1)
			{
				$('#liEdit').before('<li>'+val+'</li>');
				hideEdit();
			}
		}, 'json');
	});

	$('.butCancel').click(function () {
		hideEdit();
	});
}

function linkEdit()
{
	$('.butSave').click(function () {
		data = {
			'cs': 'attribute',
			'ca': 'update',
			'type': type,
			'id': id
		};

		$('.edit').each(function () {
			data[$(this).attr('name')] = $(this).val();
		});

		$.post('index.php', data, function (res) {
			if (res.res == 1)
			{
				$.each(targets, function (k, v) {
					v.text(res[k]);
				});
				hideEdit();
			}
		}, 'json');
	});

	$('.butCancel').click (function () {
		hideEdit();
	});
}

function sendDelete(type, id)
{
	$.post('index.php', {
		'cs': 'attribute',
		'ca': 'delete',
		'type': type,
		'id': id
	}, function (res) {
		if (res.res == 1)
		{
			$('#li'+type+'\\.'+id).hide(500, function () { $(this).remove(); });
		}
	}, 'json');
}