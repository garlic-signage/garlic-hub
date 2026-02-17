/**
 * 	Abstract class for inherit Property classes
 *	Todo Later if such thing is possible/necessary in JS
 */
class AbstractProperties
{
	MyCanvasView = {};
	property     = undefined;

	constructor()
	{
		if (new.target === AbstractProperties)
			throw new TypeError("Cannot construct Abstract instances directly");
	}

}
