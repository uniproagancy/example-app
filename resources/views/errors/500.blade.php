@extends('errors::minimal')

@section('title', __('errors.500.title'))
@section('code', '500')
@section('message', __('errors.500.text'))
@section('reload', true)
