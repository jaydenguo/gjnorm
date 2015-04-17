<?php namespace Gjnorm\Exception;

class ModelNotFoundException extends \RuntimeException {

	/**
	 * Name of the affected Eloquent model.
	 *
	 * @var string
	 */
	protected $model;

	/**
	 * Set the affected Eloquent model.
	 *
	 * @param  string   $model
	 * @return $this
	 */
	public function setModel($model)
	{
		$this->model = $model;

		$this->message = "消息: 没有符合条件的记录; 模型: [{$model}].";

		return $this;
	}

	/**
	 * Get the affected Eloquent model.
	 *
	 * @return string
	 */
	public function getModel()
	{
		return $this->model;
	}

}
