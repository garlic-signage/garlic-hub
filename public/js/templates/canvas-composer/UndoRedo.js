fabric.Canvas.prototype._historyNext = function () {
	return JSON.stringify(this.toJSON(['evented', 'role', 'selectable', 'originalSize']))
}
fabric.Canvas.prototype._historyEvents = function () {
	return {
		'object:modified': this._historySaveAction
	}
}

fabric.Canvas.prototype._historySaveAction = function () {
	if (this.historyProcessing)
		return
	const json = this.historyNextState
	this.historyUndo.push(json)
	// limit max states to 20
	if (this.historyUndo.length > 20) {
		this.historyUndo.shift()
	}
	this.historyNextState = this._historyNext()
}
fabric.Canvas.prototype.undo = function (callback) {
	this.historyProcessing = true
	const history = this.historyUndo.length > 1 ? this.historyUndo.pop() : null
	if (history) {
		this.historyRedo.push(this._historyNext())
		this.historyNextState = history
		this._loadHistory(history, 'history:undo', callback)
	} else {
		this.historyProcessing = false
	}
}

fabric.Canvas.prototype.redo = function (callback) {
	this.historyProcessing = true
	const history = this.historyRedo.pop()
	if (history) {
		this.historyUndo.push(this._historyNext())
		this.historyNextState = history
		this._loadHistory(history, 'history:redo', callback)
	} else {
		this.historyProcessing = false
	}
},
	fabric.Canvas.prototype._loadHistory = function (history, event, callback) {
		this.loadFromJSON(history, () => {
			this.renderAll()
			this.fire(event)
			this.historyProcessing = false

			if (callback && typeof callback === 'function')
				callback()
		})
	}
fabric.Canvas.prototype.historyUndo = []
fabric.Canvas.prototype.historyRedo = []
fabric.Canvas.prototype.historyNextState = fabric.Canvas.prototype._historyNext()
fabric.Canvas.prototype.on(fabric.Canvas.prototype._historyEvents())
