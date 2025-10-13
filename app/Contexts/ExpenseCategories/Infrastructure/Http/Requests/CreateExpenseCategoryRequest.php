<?php

namespace App\Contexts\ExpenseCategories\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:expense_categories,name',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la categoría es obligatorio',
            'name.unique' => 'Ya existe una categoría con ese nombre',
            'name.max' => 'El nombre no puede tener más de 255 caracteres',
            'description.max' => 'La descripción no puede tener más de 1000 caracteres',
        ];
    }
}
